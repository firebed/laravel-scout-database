<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScoutIndex extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('scout_index', function (Blueprint $table) {
            $table->string('index');
            $table->string('objectID');
            $table->text('keywords');
            $table->unique(['index', 'objectID']);
        });
        
        DB::statement("ALTER TABLE `scout_index` ADD FULLTEXT search(`keywords`)");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('scout_index');
    }
}