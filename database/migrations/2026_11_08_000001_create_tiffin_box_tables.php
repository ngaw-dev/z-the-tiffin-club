<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_meals', function (Blueprint $table) {
            $table->id();
            $table->date('menu_date')->unique();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 8, 2);
            $table->string('photo_path')->nullable();
            $table->unsignedTinyInteger('max_per_order')->default(5);
            $table->timestamps();
        });

        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_meal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer'); // customer | owner
            $table->string('phone')->nullable()->after('role');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fulfilment_type'); // delivery | pickup
            $table->string('status')->default('received'); // received|preparing|out_for_delivery|ready_for_pickup|completed|cancelled
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('delivery_address')->nullable();
            $table->string('payment_method')->default('cash'); // cash | card_on_handover
            $table->decimal('order_total', 8, 2);
            $table->timestamp('ordered_at');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type'); // meal | extra
            $table->foreignId('daily_meal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('extra_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // snapshot
            $table->decimal('unit_price', 8, 2); // snapshot
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('extras');
        Schema::dropIfExists('daily_meals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone']);
        });
    }
};
