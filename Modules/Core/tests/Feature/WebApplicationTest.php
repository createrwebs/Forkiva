<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebApplicationTest extends TestCase
{
    private string $originalStorage;

    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStorage = storage_path();
        $this->temporaryStorage = sys_get_temp_dir().'/forkiva-web-storage-'.Str::uuid();
        File::makeDirectory($this->temporaryStorage.'/framework', 0755, true);
        app()->useStoragePath($this->temporaryStorage);
        File::put(storage_path('framework/installed.json'), '{}');
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        app()->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    public static function browserPaths(): array
    {
        return array_map(fn (string $path): array => [$path], [
            '/',
            '/auth/login',
            '/admin/products/1/edit',
            '/admin/accounting',
            '/employee',
            '/driver',
            '/online-menu/example',
            '/table-order/00000000-0000-4000-8000-000000000000',
            '/customer-display',
            '/admin/pos/viewer',
            '/offline',
        ]);
    }

    #[DataProvider('browserPaths')]
    public function test_browser_deep_links_receive_the_same_public_shell(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertViewIs('app')
            ->assertSee('id="app"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_browser_shell_preserves_the_installation_gate(): void
    {
        File::delete(storage_path('framework/installed.json'));

        $this->get('/admin')->assertRedirectToRoute('installer.welcome');
    }

    public function test_unknown_api_and_asset_paths_do_not_receive_html_shells(): void
    {
        foreach (['/api/v1/not-a-route', '/build/missing.js', '/assets/missing.css', '/installer/not-a-route', '/i/not-a-uuid', '/customer-display/not-a-uuid', '/customer-display/00000000-0000-4000-8000-000000000000', '/customer-display/00000000-0000-4000-8000-000000000000/extra'] as $path) {
            $this->getJson($path)
                ->assertNotFound()
                ->assertDontSee('id="app"', false);
        }
    }

    public function test_api_authentication_and_methods_are_not_replaced_by_spa_routes(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/admin/products')->assertMethodNotAllowed();
        $this->get('/up')->assertOk()->assertDontSee('id="app"', false);
    }

    public function test_service_worker_uses_a_root_scope_and_revalidation_headers(): void
    {
        $originalPublicPath = public_path();
        $temporaryPublicPath = sys_get_temp_dir().'/forkiva-web-'.Str::uuid();
        File::makeDirectory($temporaryPublicPath.'/build', 0755, true);
        File::put($temporaryPublicPath.'/build/sw.js', '// test service worker');
        app()->usePublicPath($temporaryPublicPath);

        try {
            $this->get('/sw.js')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/javascript')
                ->assertHeader('Service-Worker-Allowed', '/')
                ->assertHeader('Cache-Control', 'max-age=0, no-cache, public');

            File::delete($temporaryPublicPath.'/build/sw.js');
            $this->get('/sw.js')->assertNotFound();
        } finally {
            app()->usePublicPath($originalPublicPath);
            File::deleteDirectory($temporaryPublicPath);
        }
    }
}
