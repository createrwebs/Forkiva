<?php

namespace Modules\Media\Models;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Kalnoy\Nestedset\NodeTrait;
use Modules\Media\Enum\MediaType;
use Modules\Media\MediaHelper;
use Modules\Media\Transformers\Api\V1\MediaSimpleResource;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property int $id
 * @property int|null $media_id
 * @property-read  Media|null $media
 * @property MediaType $type
 * @property string $name
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int|null $size
 * @property string|null $path
 * @property string|null $disk
 * @property string|null $download_url
 * @property string|null $url
 * @property string $human_size
 * @property string $preview_image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Media extends Model
{
    use HasCreatedBy, HasFilters, NodeTrait;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'media_id',
        'type',
        'name',
        'mime_type',
        'extension',
        'size',
        'path',
        'disk',
    ];

    /**
     * Get cache media
     */
    public static function getCacheMedia(?int $mediaId = null, bool $withMediaResource = false): MediaSimpleResource|null|Media
    {
        if (is_null($mediaId)) {
            return null;
        }

        $media = Cache::rememberForever(md5("medias.$mediaId"), fn () => Media::query()->find($mediaId));

        if ($withMediaResource && $media) {
            return new MediaSimpleResource($media);
        }

        return $media;
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            if ($media->isFile()) {
                Storage::disk($media->disk)->delete($media->getRawOriginal('path'));
            }
        });

        if (auth()->check()) {
            static::addCreatedByGlobalScope();
        }
    }

    /**
     * Determine if media is a file
     */
    public function isFile(): bool
    {
        return $this->type === MediaType::File;
    }

    /**
     * Determine whether the file contains active SVG/XML image content.
     */
    public function isSvg(): bool
    {
        return strtolower((string) $this->extension) === 'svg'
            || strtolower((string) $this->mime_type) === 'image/svg+xml';
    }

    /**
     * Get files inside the folder
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Check if the file has linked models.
     */
    public function hasLinked(): bool
    {
        return $this->type === MediaType::File && DB::table('model_files')->where('media_id', $this->id)->count() > 0;
    }

    /**
     * Get download for the file.
     */
    public function download(): ?StreamedResponse
    {
        return $this->isFile() ? Storage::disk($this->disk)->download($this->path, $this->getDownloadName()) : null;
    }

    /**
     * Get the file's download name.
     */
    public function getDownloadName(): string
    {
        $name = $this->name;
        $extension = $this->extension;

        return str($name)->endsWith(".$extension") ? $name : "$name.$extension";
    }

    /**
     * Get the file's url.
     */
    public function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exists && $this->isFile() && ! $this->isSvg()
                ? Storage::disk($this->disk)->url($this->path)
                : null
        );
    }

    /**
     * Get the file's content.
     */
    public function getContent(): ?string
    {
        return $this->exists && $this->isFile() ? Storage::disk($this->disk)->get($this->path) : null;
    }

    /**
     * Get the file's image base64.
     */
    public function getImageBase64(): ?string
    {
        return $this->exists && $this->isFile() && $this->isImage()
            ? 'data:image/jpg;base64,'.base64_encode(Storage::disk($this->disk)->get($this->path))
            : null;
    }

    /**
     * Determine if the file type is image.
     */
    public function isImage(): bool
    {
        return $this->isFile() && strtok($this->mime_type, '/') === 'image';
    }

    /**
     * Get the file's human size.
     */
    public function humanSize(): Attribute
    {
        return Attribute::make(
            get: fn () => humanFileSize($this->size ?? 0)
        );
    }

    /**
     * Get the file's download url.
     */
    public function downloadUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isFile()
                ? URL::temporarySignedRoute(
                    'admin.media.download',
                    now()->addMinutes(max(1, (int) config('media.download_url_ttl_minutes', 10))),
                    ['id' => $this->id],
                )
                : null
        );
    }

    /**
     * Get the file's preview image.
     */
    public function previewImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->isSvg() => URL::temporarySignedRoute(
                    'admin.media.preview',
                    now()->addMinutes(max(1, (int) config('media.download_url_ttl_minutes', 10))),
                    ['id' => $this->id],
                ),
                $this->isImage() => $this->url,
                default => MediaHelper::resolvePreviewImage($this->extension),
            }
        );
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'type',
            'mime',
            'folder',
            'tab',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->like('name', $value);
    }

    /**
     * Scope a query to only mime type.
     */
    public function scopeMime(Builder $query, string $value): void
    {
        $query->where(function ($query) use ($value) {
            $query->where(function ($query) use ($value) {
                foreach (explode(',', $value) as $mime) {
                    $query->orWhere('mime_type', 'LIKE', "$mime/%");
                }
            })->orWhere('type', MediaType::Folder);
        });
    }

    /**
     * Scope a query to only folder .
     */
    public function scopeFolder(Builder $query, string $value): void
    {
        $query->where(function ($query) use ($value) {
            $query
                ->when($value === 'root', fn ($query) => $query->whereNull('media_id'))
                ->when($value !== 'root', fn ($query) => $query->where('media_id', $value));
        });
    }

    /**
     * Scope a query to filter media files based on the selected tab type.
     */
    public function scopeTab(Builder $query, string $value): void
    {
        $query->where(function ($query) use ($value) {
            $query->when($value === 'documents', function ($q) {
                $q->where(function ($subQuery) {
                    $subQuery->where('mime_type', 'like', 'application/%')
                        ->orWhere('mime_type', 'like', 'text/%');
                });
            })
                ->when($value === 'images', function ($q) {
                    $q->where('mime_type', 'like', 'image/%');
                })
                ->when($value === 'videos', function ($q) {
                    $q->where('mime_type', 'like', 'video/%');
                })
                ->when($value === 'audio', function ($q) {
                    $q->where('mime_type', 'like', 'audio/%');
                })
                ->when($value === 'archives', function ($q) {
                    $q->whereIn('mime_type', [
                        'application/zip',
                        'application/x-rar-compressed',
                        'application/x-7z-compressed',
                        'application/x-tar',
                    ]);
                })
                ->when($value === 'spreadsheets', function ($q) {
                    $q->whereIn('mime_type', [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ]);
                })
                ->when($value === 'presentations', function ($q) {
                    $q->whereIn('mime_type', [
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    ]);
                })
                ->when($value === 'pdfs', function ($q) {
                    $q->where('mime_type', 'application/pdf');
                });
        });
    }

    /** {@inheritDoc} */
    public function getParentIdName(): string
    {
        return 'media_id';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
        ];
    }

    /** {@inheritDoc} */
    protected function deleteDescendants(): void
    {
        $lft = $this->getLft();
        $rgt = $this->getRgt();

        $this->descendants()->get()->each->delete();

        if ($this->hardDeleting()) {
            $height = $rgt - $lft + 1;

            $this->newNestedSetQuery()->makeGap($rgt + 1, -$height);

            // In case if user wants to re-create the node
            $this->makeRoot();

            static::$actionsPerformed++;
        }
    }
}
