<?php

/*
 * CREATE TABLE `customer_aliases` (
 *   `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `customer_id` INT NOT NULL,
 *   `alias_name` VARCHAR(200) NOT NULL,
 *   `created_by` INT NULL,
 *   `created_at` TIMESTAMP NULL,
 *   `updated_at` TIMESTAMP NULL,
 *   KEY `customer_aliases_customer_id_index` (`customer_id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql_write')->create('customer_aliases', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->index();
            $table->string('alias_name', 200);
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('mysql_write')->dropIfExists('customer_aliases');
    }
};
