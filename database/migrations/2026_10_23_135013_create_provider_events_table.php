
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->unsignedBigInteger('policy_request_id')->nullable()->index();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
