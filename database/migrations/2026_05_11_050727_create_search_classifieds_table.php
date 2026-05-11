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
        Schema::create('search_classifieds', function (Blueprint $table) {
            $table->char('classifiedUUID', 36)->primary();
            $table->char('creatorUUID', 36);
            $table->bigInteger('creationDate');
            $table->bigInteger('expirationDate');
            $table->string('category', 20);
            $table->string('name', 255);
            $table->text('description');
            $table->char('parcelUUID', 36);
            $table->integer('parentEstate');
            $table->char('snapshotUUID', 36);
            $table->string('simName', 255);
            $table->string('posGlobal', 255);
            $table->string('parcelName', 255);
            $table->tinyInteger('classifiedFlags');
            $table->integer('priceForListing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_classifieds');
    }
};
