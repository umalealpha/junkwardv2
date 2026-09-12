<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTbCvgpccoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Dropping existing indexes
        DB::statement("DROP INDEX IF EXISTS `s_CoverageCode` ON `tb_cvgpccoverages`;");
        DB::statement("DROP INDEX IF EXISTS `s_CoverageSection` ON `tb_cvgpccoverages`;");

        // Recreating indexes with descriptive names,drop if exists
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_coverage_code` ON `tb_cvgpccoverages`;");
        DB::statement("CREATE INDEX `idx_tb_cvgpccoverages_coverage_code` ON `tb_cvgpccoverages` (`s_CoverageCode`);");
        
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_coverage_section` ON `tb_cvgpccoverages`;");
        DB::statement("CREATE INDEX `idx_tb_cvgpccoverages_coverage_section` ON `tb_cvgpccoverages` (`s_CoverageSection`);");
    
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_policy_id` ON `tb_cvgpccoverages`;");
        DB::statement("CREATE INDEX `idx_tb_cvgpccoverages_policy_id` ON `tb_cvgpccoverages` (`policy_id`);");

        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_ParentCoverageID` ON `tb_cvgpccoverages`;");
        DB::statement("CREATE INDEX `idx_tb_cvgpccoverages_ParentCoverageID` ON `tb_cvgpccoverages` (`s_ParentCoverageID`);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_policy_id` ON `tb_cvgpccoverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_ParentCoverageID` ON `tb_cvgpccoverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_coverage_code` ON `tb_cvgpccoverages`;");
        DB::statement("DROP INDEX IF EXISTS `idx_tb_cvgpccoverages_coverage_section` ON `tb_cvgpccoverages`;");
    }
}
