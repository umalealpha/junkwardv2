<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStoreStockTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('store_stock', function (Blueprint $table) {
            $table->id();
			$table->integer('store_inteventories_id')
			->comment('store inventoeies ID');
			$table->integer('stock')->default(0)
			->comment('Stock Added');
			$table->integer('stock_add_remove')->default(0)
			->comment('0=> added , 1=>removed');
			$table->integer('user_id')->comment('Stock Added /Removed by The User');
            $table->timestamps();
			$table->index(['store_inteventories_id','stock_add_remove'],'id_remove');
		});
		\DB::unprepared('
			CREATE TRIGGER store_stocks_after_added AFTER INSERT ON `store_stock` FOR EACH ROW
            BEGIN
				DECLARE cnt INTEGER;
				DECLARE deductcnt INTEGER;
				select sum(stock) from store_stock where store_inteventories_id=NEW.store_inteventories_id and stock_add_remove=0 into cnt;
				select sum(stock) from store_stock where store_inteventories_id=NEW.store_inteventories_id and stock_add_remove=1 into deductcnt;
				update stores_inventories set counter=cnt-if(deductcnt is null,0,deductcnt) where id=NEW.store_inteventories_id;
			END
        ');
		\DB::unprepared('
			CREATE TRIGGER store_stocks_after_update AFTER UPDATE ON `store_stock` FOR EACH ROW
            BEGIN
				DECLARE cnt INTEGER;
				DECLARE deductcnt INTEGER;
				select sum(stock) from store_stock where store_inteventories_id=NEW.store_inteventories_id and stock_add_remove=0 into cnt;
				select sum(stock) from store_stock where store_inteventories_id=NEW.store_inteventories_id and stock_add_remove=1 into deductcnt;
				update stores_inventories set counter=cnt-if(deductcnt is null,0,deductcnt) where id=NEW.store_inteventories_id;
            END
        ');
		\DB::unprepared('
			CREATE TRIGGER store_stocks_before_delete BEFORE DELETE ON `store_stock` FOR EACH ROW
            BEGIN
				IF OLD.stock_add_remove=0 THEN
					update stores_inventories set counter=counter-OLD.stock where id=OLD.store_inteventories_id;
				ELSE 
					update stores_inventories set counter=counter+OLD.stock where id=OLD.store_inteventories_id;
				END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('store_stock');
		\DB::unprepared('DROP TRIGGER IF EXISTS `store_stocks_after_added`');
    	\DB::unprepared('DROP TRIGGER IF EXISTS `store_stocks_after_update`');
    	\DB::unprepared('DROP TRIGGER IF EXISTS `store_stocks_before_delete`');
    }
}
