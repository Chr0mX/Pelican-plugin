<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pfsense_autoforward_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->unique()->constrained('allocations')->cascadeOnDelete();
            // null = follow the plugin's default_protocol setting.
            $table->string('protocol')->nullable();
            // null = automatic (enabled only while the server is running),
            // true = force enabled regardless of server state, false =
            // force disabled regardless of server state.
            $table->boolean('enabled_override')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pfsense_autoforward_overrides');
    }
};
