<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWirehouseStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wirehouse_stocks', function (Blueprint $table) {
            $table->id();
			$table->integer('warehouses_inteventories_id')
			->comment('warehouses inventoeies ID');
			$table->integer('stock')->default(0)
			->comment('Stock Added');
			$table->integer('stock_add_remove')->default(0)
			->comment('0=> added , 1=>removed');
			$table->integer('user_id')->comment('Stock Added /Removed by The User');
            $table->timestamps();
			$table->index(['warehouses_inteventories_id','stock_add_remove'],'id_remove');
		});
		\DB::unprepared('
			CREATE TRIGGER wirehouse_stocks_after_added AFTER INSERT ON `wirehouse_stocks` FOR EACH ROW
            BEGIN
				DECLARE cnt INTEGER;
				DECLARE deductcnt INTEGER;
				select sum(stock) from wirehouse_stocks where warehouses_inteventories_id=NEW.warehouses_inteventories_id and stock_add_remove=0 into cnt;
				select sum(stock) from wirehouse_stocks where warehouses_inteventories_id=NEW.warehouses_inteventories_id and stock_add_remove=1 into deductcnt;
				update warehouses_inteventories set counter=cnt-if(deductcnt is null,0,deductcnt) where id=NEW.warehouses_inteventories_id;
            END
        ');
		\DB::unprepared('
			CREATE TRIGGER wirehouse_stocks_after_update AFTER UPDATE ON `wirehouse_stocks` FOR EACH ROW
            BEGIN
				DECLARE cnt INTEGER;
				DECLARE deductcnt INTEGER;
				select sum(stock) from wirehouse_stocks where warehouses_inteventories_id=NEW.warehouses_inteventories_id and stock_add_remove=0 into cnt;
				select sum(stock) from wirehouse_stocks where warehouses_inteventories_id=NEW.warehouses_inteventories_id and stock_add_remove=1 into deductcnt;
				update warehouses_inteventories set counter=cnt-if(deductcnt is null,0,deductcnt) where id=NEW.warehouses_inteventories_id;
            END
        ');
		\DB::unprepared('CREATE TRIGGER wirehouse_stocks_before_delete BEFORE DELETE ON `wirehouse_stocks` FOR EACH ROW
            BEGIN
				IF OLD.stock_add_remove=0 THEN
					update warehouses_inteventories set counter=counter-OLD.stock where id=OLD.warehouses_inteventories_id;
				ELSE 
					update warehouses_inteventories set counter=counter+OLD.stock where id=OLD.warehouses_inteventories_id;
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
        Schema::dropIfExists('wirehouse_stocks');
		\DB::unprepared('DROP TRIGGER IF EXISTS `wirehouse_stocks_after_added`');
    	\DB::unprepared('DROP TRIGGER IF EXISTS `wirehouse_stocks_after_update`');
    	\DB::unprepared('DROP TRIGGER IF EXISTS `wirehouse_stocks_after_delete`');
    }
}
