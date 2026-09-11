<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\DashboardNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_routes_match_exactly_and_not_every_dashboard_page(): void
    {
        $adminOverview = [
            'route' => 'admin',
            'match' => ['admin'],
        ];
        $userDashboard = [
            'route' => 'dashboard',
            'match' => ['dashboard'],
        ];

        $this->assertTrue(DashboardNavigation::isActive($adminOverview, 'admin'));
        $this->assertFalse(DashboardNavigation::isActive($adminOverview, 'admin.users'));
        $this->assertTrue(DashboardNavigation::isActive($userDashboard, 'dashboard'));
        $this->assertFalse(DashboardNavigation::isActive($userDashboard, 'dashboard.wallet'));
    }

    public function test_explicit_child_patterns_match_nested_pages(): void
    {
        $support = [
            'route' => 'dashboard.support.index',
            'match' => ['dashboard.support.*'],
        ];
        $products = [
            'route' => 'admin.platform-products',
            'match' => ['admin.platform-products', 'admin.platform-products.*'],
        ];

        $this->assertTrue(DashboardNavigation::isActive($support, 'dashboard.support.show'));
        $this->assertTrue(DashboardNavigation::isActive($products, 'admin.platform-products.edit'));
        $this->assertFalse(DashboardNavigation::isActive($products, 'admin.platform-categories'));
    }

    public function test_single_child_groups_flatten_to_plain_links(): void
    {
        config([
            'menus.test-role' => [
                [
                    'type' => 'group',
                    'id' => 'lonely',
                    'label' => 'Lonely',
                    'icon' => 'home',
                    'sort' => 10,
                    'children' => [
                        ['route' => 'admin', 'match' => ['admin'], 'label' => 'Child', 'icon' => 'home', 'sort' => 10],
                    ],
                ],
                [
                    'type' => 'group',
                    'id' => 'packed',
                    'label' => 'Packed',
                    'icon' => 'users',
                    'sort' => 20,
                    'children' => [
                        ['route' => 'admin.users', 'match' => ['admin.users'], 'label' => 'A', 'icon' => 'users', 'sort' => 10],
                        ['route' => 'admin.kyc', 'match' => ['admin.kyc'], 'label' => 'B', 'icon' => 'kyc', 'sort' => 20],
                    ],
                ],
            ],
        ]);

        $entries = collect(DashboardNavigation::for('test-role'))->keyBy('id');

        $this->assertSame('link', $entries['lonely']['type']);
        $this->assertSame('Child', $entries['lonely']['label']);
        $this->assertSame('admin', $entries['lonely']['route']);
        $this->assertSame('group', $entries['packed']['type']);

        $adminUser = User::factory()->admin()->create();
        $admin = collect(DashboardNavigation::for('admin', $adminUser))->keyBy('id');
        $this->assertSame('group', $admin['dashboard']['type']);
        $this->assertSame('link', $admin['support']['type']);
    }

    public function test_active_child_opens_its_parent_group(): void
    {
        $admin = User::factory()->admin()->create();
        $entries = DashboardNavigation::for('admin', $admin);
        $open = DashboardNavigation::initiallyOpenGroups($entries, 'admin.users');

        $this->assertSame(['identity'], $open);
    }

    public function test_search_index_is_permission_aware(): void
    {
        $admin = User::factory()->admin()->create();
        $index = DashboardNavigation::searchIndex('admin', $admin);
        $labels = collect($index)->pluck('label')->all();

        $this->assertContains('Platform Products', $labels);
        $this->assertContains('Users', $labels);

        $prodHits = collect($index)->filter(function (array $item) {
            return collect($item['keywords'])->contains(fn ($k) => str_contains(strtolower((string) $k), 'prod'));
        });

        $this->assertTrue($prodHits->isNotEmpty());
    }

    public function test_all_configured_menu_routes_and_icons_exist(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['admin', 'user'] as $role) {
            $user = $role === 'admin' ? $admin : User::factory()->create();
            if ($role === 'user') {
                $user->assignRole('user');
            }

            foreach (DashboardNavigation::for($role, $user) as $entry) {
                $items = ($entry['type'] ?? 'link') === 'group'
                    ? ($entry['children'] ?? [])
                    : [$entry];

                if (isset($entry['icon'])) {
                    $this->assertFileExists(resource_path('icons/'.$entry['icon'].'.svg'));
                }

                foreach ($items as $item) {
                    $this->assertTrue(Route::has($item['route']), "Missing route [{$item['route']}]");
                    $this->assertFileExists(resource_path('icons/'.$item['icon'].'.svg'));
                }
            }
        }
    }

    public function test_user_sidebar_matches_locked_ia(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $entries = collect(DashboardNavigation::for('user', $user))->keyBy('id');

        $this->assertArrayHasKey('services', $entries);
        $this->assertArrayHasKey('campaigns', $entries);
        $this->assertArrayHasKey('service-orders', $entries);
        $this->assertArrayNotHasKey('my-tools', $entries);
        $this->assertArrayHasKey('wallet', $entries);
        $this->assertArrayHasKey('support', $entries);
        $this->assertArrayHasKey('settings', $entries);
        $this->assertArrayNotHasKey('marketplace', $entries);
        $this->assertArrayNotHasKey('discover', $entries);
        $this->assertArrayNotHasKey('inbox', $entries);
    }
}
