<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('clinical', function ($blueprint): void {
            $blueprint->add('adt_notifications_channels', ['database', 'mail']);
        });
    }

    public function down(): void
    {
        $this->migrator->inGroup('clinical', function ($blueprint): void {
            $blueprint->delete('adt_notifications_channels');
        });
    }
};
