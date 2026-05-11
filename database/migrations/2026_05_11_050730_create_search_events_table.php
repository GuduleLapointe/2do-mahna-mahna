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
        Schema::create('search_events', function (Blueprint $table) {
            $table->char('ownerUUID', 36);
            $table->string('name', 255);
            $table->increments('eventID')->primary();
            $table->char('creatorUUID', 36);
            $table->tinyInteger('category');
            $table->text('description');
            $table->integer('dateUTC');
            $table->integer('duration');
            $table->boolean('coverCharge');
            $table->integer('coverAmount');
            $table->string('simName', 255);
            $table->char('parcelUUID', 36);
            $table->string('globalPos', 255);
            $table->tinyInteger('eventFlags');
            $table->string('gatekeeperURL', 255)->nullable();
            $table->string('landingPoint', 35)->nullable();
            $table->string('parcelName', 255)->nullable();
            $table->enum('mature', ['true', 'false']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_events');
    }
};
