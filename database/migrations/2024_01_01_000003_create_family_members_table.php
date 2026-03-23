<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("family_members", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->enum("gender", ["male", "female"])->default("male");
            $table->date("birth_date")->nullable();
            $table->date("death_date")->nullable();
            $table->string("wife_name")->nullable();
            $table->text("description")->nullable();
            $table->foreignId("parent_id")->nullable()->constrained("family_members")->onDelete("cascade");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("family_members");
    }
};
