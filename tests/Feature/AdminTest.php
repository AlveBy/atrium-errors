<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Support\Menu as AdminMenu;
use Atrium\Errors\Contracts\ErrorReporter;
use Atrium\Errors\Dashboard\ErrorsGadget;
use Atrium\Errors\ErrorsSettings;
use Atrium\Errors\Event;
use Atrium\Errors\Menu;
use Atrium\Errors\Models\Issue;
use Atrium\Errors\Support\Options;
use Atrium\Errors\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

/**
 * The screens: the list, an issue, its status, the menu, the dashboard tile
 * and the settings — and who may see them.
 */
final class AdminTest extends TestCase
{
    public function test_the_list_shows_unresolved_issues_by_default(): void
    {
        $open = $this->issue(['message' => 'Open one', 'times_seen' => 4, 'users_seen' => 2]);
        $this->issue(['message' => 'Done one', 'status' => Issue::RESOLVED]);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/errors')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Errors/Index')
                ->where('filters.status', 'new')
                ->has('issues.data', 1)
                ->where('issues.data.0.id', $open->id)
                ->where('issues.data.0.short_class', 'RoomUnavailable')
                ->where('issues.data.0.times_seen', 4)
                ->where('issues.data.0.users_seen', 2)
                ->where('issues.data.0.url', route('admin.errors.show', $open->id))
                ->where('options.statuses', ['new', 'resolved', 'ignored'])
                ->where('options.releases', ['2.4.0'])
                ->where('strings.title', 'Errors')
                ->where('strings.issue.resolve_next', 'Resolve in next release'));
    }

    public function test_the_list_is_filtered_and_sorted(): void
    {
        $site = Site::query()->create(['name' => 'Second site', 'code' => 'second', 'domains' => ['second.example.test'], 'is_active' => true]);

        $checkout = $this->issue(['class' => 'App\\Checkout\\PaymentFailed', 'message' => 'Gateway timeout', 'level' => 'fatal', 'times_seen' => 9]);
        $this->occurrence($checkout, ['path' => '/checkout', 'release' => '2.3.0', 'site_id' => $site->id]);
        $browser = $this->issue(['kind' => 'browser', 'class' => 'TypeError', 'message' => 'x is undefined', 'level' => 'error', 'times_seen' => 30, 'culprit' => 'renderGallery (/build/app.js)']);
        $this->occurrence($browser, ['path' => '/gallery']);
        $ignored = $this->issue(['status' => Issue::IGNORED, 'message' => 'Bot noise']);

        $this->actingAs($this->admin(), 'admin');
        $ids = fn (string $query): array => collect($this->get('/admin/errors?'.$query)->assertOk()->viewData('page')['props']['issues']['data'])->pluck('id')->all();

        $this->assertSame([$checkout->id], $ids('q=gateway'));
        $this->assertSame([$browser->id], $ids('q=renderGallery'));
        $this->assertSame([], $ids('q=100%25'));
        $this->assertSame([$checkout->id], $ids('level=fatal'));
        $this->assertSame([$browser->id], $ids('kind=browser'));
        $this->assertSame([$checkout->id], $ids('site='.$site->id));
        $this->assertSame([$checkout->id], $ids('release=2.3.0'));
        $this->assertSame([$checkout->id], $ids('path=checkout?step=2'));
        $this->assertSame([$browser->id, $checkout->id], $ids('sort=times_seen'));
        $this->assertSame([$ignored->id], $ids('status=ignored'));
        $this->assertCount(3, $ids('status=all'));
        // Unknown values fall back to the defaults instead of failing.
        $this->assertCount(2, $ids('status=deleted&level=loud&kind=cron&sort=name&site=abc'));
    }

    public function test_an_issue_shows_its_last_event_occurrences_and_chart(): void
    {
        $issue = $this->issue(['times_seen' => 2, 'resolved_in' => null]);
        $old = $this->occurrence($issue, ['created_at' => now()->subHours(3), 'path' => '/rooms/1', 'user_id' => 7]);
        $new = $this->occurrence($issue, ['path' => '/rooms/2', 'site_id' => 999]);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/errors/'.$issue->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Errors/Show')
                ->where('issue.id', $issue->id)
                ->where('issue.message', $issue->message)
                ->where('event.id', $new->id)
                ->where('event.site', '#999')
                ->has('event.uuid')
                ->has('event.exceptions.0.frames')
                ->has('occurrences.data', 2)
                ->where('occurrences.data.1.user_id', 7)
                ->where('top.paths', [['value' => '/rooms/1', 'count' => 1], ['value' => '/rooms/2', 'count' => 1]])
                ->where('top.releases', [['value' => '2.4.0', 'count' => 2]])
                ->has('chart.hourly', 24)
                ->has('chart.daily', 30)
                ->where('chart.hourly.23.value', 1)
                ->where('chart.hourly.20.value', 1)
                ->where('currentRelease', '2.4.0')
                ->missing('monitoring'));

        $this->get('/admin/errors/'.$issue->id.'?event='.$old->id)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('event.id', $old->id));
        $this->get('/admin/errors/'.$issue->id.'?event=999999')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('event.id', $new->id));
    }

    public function test_an_issue_whose_events_were_pruned_still_opens(): void
    {
        $issue = $this->issue();

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/errors/'.$issue->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('event', null));

        $this->get('/admin/errors/999999')->assertNotFound();
        $this->get('/admin/errors/abc')->assertNotFound();
    }

    public function test_the_status_is_changed(): void
    {
        $issue = $this->issue();
        $this->actingAs($this->admin(), 'admin')->from('/admin/errors/'.$issue->id);

        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'resolved'])
            ->assertRedirect('/admin/errors/'.$issue->id)
            ->assertSessionHas('success', 'The error is resolved.');
        $issue->refresh();
        $this->assertSame(Issue::RESOLVED, $issue->status);
        $this->assertNotNull($issue->resolved_at);
        $this->assertNull($issue->resolved_in);

        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'resolved', 'next_release' => true]);
        $this->assertSame('2.4.0', $issue->refresh()->resolved_in);

        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'ignored'])->assertSessionHas('success', 'The error is ignored.');
        $issue->refresh();
        $this->assertSame(Issue::IGNORED, $issue->status);
        $this->assertNull($issue->resolved_at);
        $this->assertNull($issue->resolved_in);

        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'new'])->assertSessionHas('success', 'The error is open again.');
        $this->assertSame(Issue::NEW, $issue->refresh()->status);
    }

    public function test_a_wrong_status_is_refused(): void
    {
        $issue = $this->issue();
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'deleted'])->assertSessionHasErrors('status');
        $this->post('/admin/errors/'.$issue->id.'/status', [])->assertSessionHasErrors('status');
        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'resolved', 'next_release' => 'maybe'])->assertSessionHasErrors('next_release');

        $this->assertSame(Issue::NEW, $issue->refresh()->status);
    }

    public function test_only_administrators_with_the_permission_get_in(): void
    {
        $issue = $this->issue();

        $this->get('/admin/errors')->assertRedirect();

        $this->actingAs($this->editor(['news.view']), 'admin');
        $this->get('/admin/errors')->assertForbidden();
        $this->get('/admin/errors/'.$issue->id)->assertForbidden();
        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'ignored'])->assertForbidden();
        $this->assertSame(Issue::NEW, $issue->refresh()->status);

        // Settings alone do not open the errors: they are a separate right.
        $this->actingAs($this->editor(['errors.settings']), 'admin');
        $this->get('/admin/errors')->assertForbidden();
        $this->get('/admin/modules/errors/settings')->assertOk();

        // Support: reads and triages errors, may not change where they go.
        $this->actingAs($this->editor(['errors.view']), 'admin');
        $this->get('/admin/errors')->assertOk();
        $this->get('/admin/errors/'.$issue->id)->assertOk();
        $this->post('/admin/errors/'.$issue->id.'/status', ['status' => 'ignored'])->assertRedirect();
        $this->assertSame(Issue::IGNORED, $issue->refresh()->status);
        $this->get('/admin/modules/errors/settings')->assertForbidden();
        $this->put('/admin/modules/errors/settings', ['drivers' => ['log']])->assertForbidden();
        $this->assertSame(['database'], $this->app->make(Options::class)->drivers());
    }

    public function test_both_rights_are_in_the_role_form_and_view_comes_with_settings(): void
    {
        $section = collect($this->app->make(Permissions::class)->sections())->firstWhere('key', 'errors');

        $this->assertSame(['settings', 'view'], $section['actions']);
        $this->assertSame('View errors', $section['labels']['view']);

        $module = Module::fromPath(dirname(__DIR__, 2));
        $this->assertSame(['errors.view' => ['label' => 'atrium-errors::errors.permissions.view', 'with' => 'errors.settings']], $module?->declaredPermissions);

        // The core's installer gives "view" to the roles that may set the module up.
    }

    public function test_an_administrator_of_one_site_sees_only_its_errors(): void
    {
        $main = Site::query()->where('is_default', true)->firstOrFail();
        $other = Site::query()->create(['code' => 'coast', 'name' => 'Coast Hotels', 'domains' => ['coast.example.test'], 'languages' => ['en']]);
        $mine = $this->issue(['class' => 'App\\Mine']);
        $this->occurrence($mine, ['site_id' => $main->id, 'path' => '/mine', 'release' => '2.4.0']);
        $theirs = $this->issue(['class' => 'App\\Theirs', 'last_release' => '9.9.9']);
        $theirEvent = $this->occurrence($theirs, ['site_id' => $other->id, 'path' => '/theirs', 'release' => '9.9.9']);
        $shared = $this->issue(['class' => 'App\\Shared']);
        $sharedHere = $this->occurrence($shared, ['site_id' => $main->id, 'path' => '/shared-main']);
        $this->occurrence($shared, ['site_id' => $other->id, 'path' => '/shared-coast']);
        $console = $this->issue(['class' => 'App\\Console']);
        $this->occurrence($console, ['site_id' => null]);

        $editor = $this->editor(['errors.view']);
        $editor->update(['site_ids' => [(string) $main->id]]);
        $this->actingAs($editor, 'admin');

        $this->get('/admin/errors?status=all')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('issues.data', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all() === [$mine->id, $shared->id])
            ->where('options.sites', [['id' => $main->id, 'name' => $main->name]])
            ->where('options.releases', ['2.4.0']));
        $this->get('/admin/errors/'.$theirs->id)->assertNotFound();
        $this->get('/admin/errors/'.$console->id)->assertNotFound();
        $this->post('/admin/errors/'.$theirs->id.'/status', ['status' => 'ignored'])->assertNotFound();
        $this->assertSame(Issue::NEW, $theirs->refresh()->status);

        // A shared bug opens with this site's events only, even when asked for the other one.
        $this->get('/admin/errors/'.$shared->id.'?event='.$theirEvent->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('event.id', $sharedHere->id)
            ->has('occurrences.data', 1)
            ->where('top.paths', [['value' => '/shared-main', 'count' => 1]])
            ->where('chart.hourly.23.value', 1));

        $tile = (new ErrorsGadget)->data();
        $this->assertSame(2, $tile['events']);
        $this->assertSame(2, $tile['unresolved']);
        $this->assertEqualsCanonicalizing([$mine->id, $shared->id], array_column($tile['top'], 'id'));

        // An administrator of every site sees them all, and the errors outside any site.
        $this->actingAs($this->editor(['errors.view']), 'admin');
        $this->get('/admin/errors?status=all')->assertInertia(fn (AssertableInertia $page) => $page->has('issues.data', 4)->has('options.sites', 2));
        $this->get('/admin/errors/'.$console->id)->assertOk();
        $this->assertSame(5, (new ErrorsGadget)->data()['events']);
    }

    public function test_the_menu_gets_a_site_health_group(): void
    {
        $items = $this->app->make(AdminMenu::class)->items($this->admin());
        $group = collect($items)->firstWhere('key', Menu::GROUP);

        $this->assertSame('Site health', $group['label']);
        $this->assertSame([['label' => 'Errors', 'route' => 'admin.errors.index', 'icon' => 'info']], $group['children']);
        // Before "Modules", which stays last.
        $this->assertSame('admin.modules.index', end($items)['route']);

        $this->assertNull(collect($this->app->make(AdminMenu::class)->items($this->editor(['news.view'])))->firstWhere('key', Menu::GROUP));
    }

    public function test_the_menu_joins_a_site_health_group_another_module_made(): void
    {
        $monitoring = [['label' => 'Dashboard', 'route' => 'admin.dashboard'], ['key' => 'site-health', 'label' => 'Site health', 'children' => [['label' => 'Speed', 'route' => 'admin.monitoring.pages']]]];

        $menu = Menu::add($monitoring);

        $this->assertSame(['admin.monitoring.pages', 'admin.errors.index'], array_column($menu[1]['children'], 'route'));
        $this->assertSame($menu, Menu::add($menu), 'Added once');
        $this->assertSame([['label' => 'X'], ['key' => 'site-health', 'label' => 'atrium-errors::errors.health', 'icon' => 'chart', 'children' => [['label' => 'atrium-errors::errors.title', 'route' => 'admin.errors.index', 'icon' => 'info']]]], Menu::add([['label' => 'X']]));
    }

    public function test_the_dashboard_tile_counts_the_last_day(): void
    {
        $noisy = $this->issue(['class' => 'App\\Feeds\\Timeout', 'first_seen_at' => now()->subHours(2)]);
        foreach (range(1, 3) as $i) {
            $this->occurrence($noisy);
        }
        $back = $this->issue(['regressed_at' => now()->subHour(), 'first_seen_at' => now()->subDays(5)]);
        $this->occurrence($back);
        $this->occurrence($back, ['created_at' => now()->subDays(2)]);
        $this->issue(['status' => Issue::RESOLVED, 'first_seen_at' => now()->subDays(9)]);

        $gadget = new ErrorsGadget(['width' => 'full', 'title' => 'Bugs']);
        $data = $gadget->data();

        $this->assertSame('ErrorsGadget', $gadget->component());
        $this->assertSame('Bugs', $gadget->title());
        $this->assertSame('full', $gadget->width());
        $this->assertSame('half', (new ErrorsGadget)->width());
        $this->assertSame('Errors in 24 hours', (new ErrorsGadget)->title());
        $this->assertSame(4, $data['events']);
        $this->assertSame(1, $data['new']);
        $this->assertSame(1, $data['regressed']);
        $this->assertSame(2, $data['unresolved']);
        $this->assertSame([3, 1], array_column($data['top'], 'count'));
        $this->assertSame('Timeout', $data['top'][0]['class']);
        $this->assertSame(route('admin.errors.show', $noisy->id), $data['top'][0]['url']);
        $this->assertCount(24, $data['chart']);
        $this->assertSame(4, $data['chart'][23]['value']);
        $this->assertSame('No errors in the last 24 hours.', $data['strings']['none']);
    }

    public function test_the_tile_is_on_the_dashboard_for_those_who_may_see_errors(): void
    {
        $this->assertContains(ErrorsGadget::class, config('atrium.dashboard.gadgets'));

        $this->actingAs($this->admin(), 'admin');
        $this->assertTrue((new ErrorsGadget)->isVisible());
        $this->get('/admin')->assertInertia(fn (AssertableInertia $page) => $page->where('gadgets', fn ($gadgets): bool => collect($gadgets)->contains('component', 'ErrorsGadget')));

        $this->actingAs($this->editor(['news.view']), 'admin');
        $this->assertFalse((new ErrorsGadget)->isVisible());
        $this->assertFalse((new ErrorsGadget(['enabled' => false]))->isVisible());
        $this->assertInstanceOf(Gadget::class, new ErrorsGadget);
    }

    public function test_the_settings_page_lists_every_setting_and_saves_them(): void
    {
        config(['atrium-errors.reporters' => ['rollbar' => PipelineTestReporter::class]]);
        $fields = collect((new ErrorsSettings)->fields())->map(static fn ($field) => $field->toArray());

        $this->assertSame(
            ['drivers', 'sentry_dsn', 'sample_rate', 'per_minute', 'retention_days', 'alert_email', 'telegram_token', 'telegram_chat', 'alert_new', 'alert_regression', 'spike_per_hour', 'browser_site', 'browser_admin'],
            $fields->pluck('name')->all(),
        );
        $drivers = $fields->firstWhere('name', 'drivers');
        $this->assertStringContainsString('Sentry or GlitchTip', (string) json_encode($drivers));
        $this->assertStringContainsString('rollbar', (string) json_encode($drivers));
        $this->assertStringContainsString('ATRIUM_ERRORS_SENTRY_DSN', (string) json_encode($fields));

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/modules/errors/settings')
            ->assertOk();

        $this->put('/admin/modules/errors/settings', ['drivers' => ['database', 'log'], 'sentry_dsn' => 'not a dsn', 'sample_rate' => 101, 'telegram_token' => 'nope', 'telegram_chat' => 'x y'])
            ->assertSessionHasErrors(['sentry_dsn', 'sample_rate', 'telegram_token', 'telegram_chat']);

        $this->put('/admin/modules/errors/settings', ['drivers' => ['database', 'log'], 'sentry_dsn' => 'https://key@sentry.example.test/5', 'sample_rate' => 50, 'per_minute' => 30, 'retention_days' => 30, 'telegram_token' => '123:abc_DEF', 'telegram_chat' => '@north_ops', 'alert_new' => true, 'alert_regression' => false, 'spike_per_hour' => 10, 'browser_site' => true, 'browser_admin' => false])
            ->assertSessionDoesntHaveErrors();

        $options = $this->app->make(Options::class);
        $options->flush();
        $this->assertSame(['database', 'log'], $options->drivers());
        $this->assertSame(50, $options->int('sample_rate'));
        $this->assertFalse($options->bool('alert_regression'));
        $this->assertTrue($options->bool('browser_site'));
    }

    public function test_the_mail_module_knows_the_alert_purpose(): void
    {
        $this->assertSame('atrium-errors::errors.alerts.purpose', config('atrium-mail.purposes.errors'));
        $this->assertSame('Error alerts', __(config('atrium-mail.purposes.errors')));
    }
}

final class PipelineTestReporter implements ErrorReporter
{
    public function report(Event $event): void {}
}
