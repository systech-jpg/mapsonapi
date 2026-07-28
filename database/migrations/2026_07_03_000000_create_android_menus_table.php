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
        Schema::create('android_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('route')->nullable();
            
            // Storing roles as a JSON array e.g., ["Warehouse", "Super Admin"]
            $table->json('allowed_roles')->nullable(); 
            
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Self-referencing foreign key for nested menus
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('android_menus')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('android_menus');
    }
};
