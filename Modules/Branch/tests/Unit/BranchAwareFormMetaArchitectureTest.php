<?php

namespace Modules\Branch\Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchAwareFormMetaArchitectureTest extends TestCase
{
    #[Test]
    public function branch_aware_form_meta_services_use_the_central_branch_access_contract(): void
    {
        $violations = [];

        foreach (File::allFiles($this->backendPath('Modules')) as $file) {
            $path = $file->getPathname();
            if (! str_contains($path, '/app/Services/') || str_ends_with($path, 'Interface.php')) {
                continue;
            }

            $contents = File::get($path);
            if (! preg_match('/public function getFormMeta\s*\([^)]*\$[A-Za-z]*branch[A-Za-z]*Id[^)]*\)/is', $contents)) {
                continue;
            }

            if (! str_contains($contents, 'BranchAccessServiceInterface')) {
                $violations[] = str($path)->after($this->backendPath().DIRECTORY_SEPARATOR)->toString();
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    #[Test]
    public function form_meta_controllers_delegate_assigned_branch_resolution_to_services(): void
    {
        $violations = [];

        foreach (File::allFiles($this->backendPath('Modules')) as $file) {
            $path = $file->getPathname();
            if (! str_contains($path, '/app/Http/Controllers/')) {
                continue;
            }

            $method = $this->methodSource(File::get($path), 'getFormMeta');
            if ($method !== null && str_contains($method, 'assignedToBranch')) {
                $violations[] = str($path)->after($this->backendPath().DIRECTORY_SEPARATOR)->toString();
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    private function methodSource(string $source, string $methodName): ?string
    {
        $tokens = token_get_all($source);
        $capturing = false;
        $waitingForBody = false;
        $depth = 0;
        $method = '';

        foreach ($tokens as $index => $token) {
            if (! $capturing && is_array($token) && $token[0] === T_FUNCTION) {
                for ($offset = $index + 1; isset($tokens[$offset]); $offset++) {
                    $candidate = $tokens[$offset];
                    if (is_array($candidate) && $candidate[0] === T_STRING) {
                        if ($candidate[1] === $methodName) {
                            $capturing = true;
                            $waitingForBody = true;
                        }

                        break;
                    }
                }
            }

            if (! $capturing) {
                continue;
            }

            $text = is_array($token) ? $token[1] : $token;
            $method .= $text;

            if ($text === '{') {
                $waitingForBody = false;
                $depth++;
            } elseif ($text === '}' && ! $waitingForBody) {
                $depth--;
                if ($depth === 0) {
                    return $method;
                }
            }
        }

        return null;
    }

    private function backendPath(string $path = ''): string
    {
        return dirname(__DIR__, 4).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }
}
