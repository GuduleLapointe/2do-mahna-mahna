<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('search_parcels', function (Blueprint $table) {
            $table->char('parcelUUID', 36);
            $table->char('regionUUID', 36);
            $table->string('parcelName', 255);
            $table->string('landingPoint', 255);
            $table->string('description', 255);
            $table->string('searchCategory', 50);
            $table->enum('build', ['true', 'false']);
            $table->enum('script', ['true', 'false']);
            $table->enum('public', ['true', 'false']);
            $table->float('dwell')->default(0);
            $table->char('infoUUID', 36)->default('');
            $table->string('mature', 10)->default('PG');
            $table->string('gatekeeperURL', 255)->nullable();
            $table->char('imageUUID', 36)->nullable();

            $table->primary(['regionUUID', 'parcelUUID']);
            $table->index('parcelName');
            $table->index('description');
            $table->index('searchCategory');
            $table->index('dwell');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_parcels');
    }
};
