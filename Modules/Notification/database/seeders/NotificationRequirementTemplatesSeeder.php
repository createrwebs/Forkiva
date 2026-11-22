<?php

namespace Modules\Notification\Database\Seeders;

use Modules\Notification\Database\Seeders\NotificationTemplates\NotificationRequirementTemplateCatalog;

class NotificationRequirementTemplatesSeeder extends NotificationTemplateSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (NotificationRequirementTemplateCatalog::all() as $template) {
            $this->seedTemplate($template);
        }
    }
}
