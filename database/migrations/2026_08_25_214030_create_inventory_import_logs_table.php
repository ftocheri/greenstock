<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->timestamp('ran_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_import_logs');
    }
};
