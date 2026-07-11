<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->string('voter_hash');
            $table->timestamps();
            $table->unique(['entry_id', 'voter_hash'], 'votes_entry_voter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
