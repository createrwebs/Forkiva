<?php

namespace Modules\Branch\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Branch\Models\Branch;
use Modules\User\Models\User;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Throwable;

class BranchAwareFormMetaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_lists_only_filter_assigned_users_when_requested(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        auth()->setUser(User::factory()->create(['branch_id' => $assignedBranch->id]));

        $this->assertEqualsCanonicalizing(
            [$assignedBranch->id, $otherBranch->id],
            Branch::list()->pluck('id')->all(),
        );
        $this->assertSame(
            [$assignedBranch->id],
            Branch::list(true)->pluck('id')->all(),
        );
        $this->assertSame(
            [$assignedBranch->id],
            Branch::listWithLocation(true)->pluck('id')->all(),
        );
    }

    public function test_every_branch_aware_form_meta_resolves_and_enforces_the_assigned_branch(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        auth()->setUser(User::factory()->create(['branch_id' => $assignedBranch->id]));
        $failures = [];
        $services = $this->branchAwareFormMetaServices();

        $this->assertNotEmpty($services);

        foreach ($services as $serviceClass) {
            try {
                $meta = app($serviceClass)->getFormMeta(null);
                $branchIds = collect($meta['branches'] ?? [])->pluck('id')->all();
                if ($branchIds !== [] && $branchIds !== [$assignedBranch->id]) {
                    $failures[] = "{$serviceClass}: exposed branches outside the user's assigned branch";
                }
            } catch (Throwable $exception) {
                $failures[] = $serviceClass.': assigned branch resolution failed with '
                    .$exception::class.': '.$exception->getMessage();

                continue;
            }

            try {
                app($serviceClass)->getFormMeta($otherBranch->id);
                $failures[] = "{$serviceClass}: accepted a branch other than the user's assigned branch";
            } catch (HttpException $exception) {
                if ($exception->getStatusCode() !== 404) {
                    $failures[] = "{$serviceClass}: rejected another branch with HTTP {$exception->getStatusCode()} instead of 404";
                }
            } catch (Throwable $exception) {
                $failures[] = $serviceClass.': rejected another branch with '
                    .$exception::class.' instead of HTTP 404';
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }

    /**
     * @return array<int, class-string>
     */
    private function branchAwareFormMetaServices(): array
    {
        $services = [];

        foreach (File::allFiles(base_path('Modules')) as $file) {
            // Normalize Windows separators so the service scan works on every platform.
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_contains($path, '/app/Services/') || str_ends_with($path, 'Interface.php')) {
                continue;
            }

            $contents = File::get($path);
            if (! preg_match('/namespace\s+([^;]+);/', $contents, $namespace)
                || ! preg_match('/class\s+([A-Za-z0-9_]+)/', $contents, $className)) {
                continue;
            }

            $class = $namespace[1].'\\'.$className[1];
            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $reflection->isInstantiable() || ! $reflection->hasMethod('getFormMeta')) {
                continue;
            }

            $method = $reflection->getMethod('getFormMeta');
            if (! $method->isPublic() || ! $this->firstParameterIsBranchId($method)) {
                continue;
            }

            $services[] = $class;
        }

        sort($services);

        return $services;
    }

    private function firstParameterIsBranchId(ReflectionMethod $method): bool
    {
        $parameter = $method->getParameters()[0] ?? null;

        return $parameter !== null
            && str_contains(strtolower($parameter->getName()), 'branch')
            && str_ends_with(strtolower($parameter->getName()), 'id');
    }
}
