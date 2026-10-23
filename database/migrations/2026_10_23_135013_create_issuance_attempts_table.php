
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_request_id')->index();
            $table->unsignedInteger('attempt_number');
            $table->string('scenario')->default('SUCCESS');
            $table->string('outcome')->default('STARTED');
            $table->string('provider_reference')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['policy_request_id', 'attempt_number'],
                'issuance_attempt_request_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_attempts');
    }
};
