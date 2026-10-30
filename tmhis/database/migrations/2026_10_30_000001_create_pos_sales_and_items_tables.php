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
        if (!Schema::hasTable('pos_sales')) {
            Schema::create('pos_sales', function (Blueprint $table) {
                $table->increments('SaleID');
                $table->string('ReceiptNumber', 50)->unique();
                $table->enum('CustomerType', ['Walk-in', 'Patient'])->default('Walk-in');
                $table->unsignedInteger('PatientID')->nullable()->index();
                $table->string('CustomerName', 150)->default('Walk-in Customer');
                $table->string('CustomerPhone', 50)->nullable();
                $table->decimal('SubTotal', 10, 2)->default(0.00);
                $table->string('DiscountType', 50)->default('None');
                $table->decimal('DiscountAmount', 10, 2)->default(0.00);
                $table->decimal('TotalAmount', 10, 2)->default(0.00);
                $table->decimal('AmountReceived', 10, 2)->default(0.00);
                $table->decimal('ChangeAmount', 10, 2)->default(0.00);
                $table->string('PaymentMethod', 50)->default('Cash');
                $table->string('ReferenceNumber', 100)->nullable();
                $table->unsignedInteger('CashierID')->nullable();
                $table->string('CashierName', 100)->default('Cashier on Duty');
                $table->enum('Status', ['Completed', 'Cancelled', 'Refunded'])->default('Completed')->index();
                $table->text('Notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pos_sale_items')) {
            Schema::create('pos_sale_items', function (Blueprint $table) {
                $table->increments('ItemID');
                $table->unsignedInteger('SaleID')->index();
                $table->unsignedInteger('InventoryID')->index();
                $table->string('ItemCode', 50);
                $table->string('MedicineName', 255);
                $table->string('DosageForm', 80)->nullable();
                $table->string('Strength', 80)->nullable();
                $table->decimal('UnitPrice', 10, 2)->default(0.00);
                $table->integer('Quantity')->default(1);
                $table->decimal('SubTotal', 10, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('SaleID')
                      ->references('SaleID')
                      ->on('pos_sales')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
    }
};

