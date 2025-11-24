<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AFV MVP (S5): "Teams" service - specialists, team requests and monthly analysis requests
return new class extends Migration {

    public function up()
    {
        Schema::create('specialist_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedInteger('user_id');
            $table->enum('role', ['financial_analyst', 'accountant', 'controller', 'business_analyst', 'data_analyst', 'team_lead']);
            $table->enum('seniority', ['junior', 'mid', 'senior'])->default('mid');
            $table->decimal('hourly_rate', 8, 2)->default(0);
            $table->unsignedSmallInteger('weekly_capacity_hours')->default(40);
            $table->string('languages')->nullable();
            $table->json('skills')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('team_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->string('title');
            $table->enum('team_size', ['1', '2', '5', '10', '20', '50', 'custom'])->default('1');
            $table->unsignedSmallInteger('custom_size')->nullable();
            $table->json('roles')->nullable();
            $table->enum('engagement', ['hours_per_week', 'full_time'])->default('hours_per_week');
            $table->unsignedSmallInteger('hours_per_week')->nullable();
            $table->date('start_date')->nullable();
            $table->unsignedSmallInteger('duration_months')->default(3);
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'reviewing', 'proposed', 'active', 'paused', 'completed', 'rejected'])->default('new');
            $table->decimal('monthly_price_estimate', 12, 2)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('client_id');
        });

        Schema::create('team_request_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('team_request_id');
            $table->foreign('team_request_id')->references('id')->on('team_requests')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('specialist_profile_id');
            $table->foreign('specialist_profile_id')->references('id')->on('specialist_profiles')->onDelete('cascade')->onUpdate('cascade');
            $table->string('role', 40)->nullable();
            $table->unsignedSmallInteger('hours_per_week')->default(0);
            $table->timestamps();

            $table->unique(['team_request_id', 'specialist_profile_id'], 'team_request_members_unique');
        });

        Schema::create('team_request_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('team_request_id');
            $table->foreign('team_request_id')->references('id')->on('team_requests')->onDelete('cascade')->onUpdate('cascade');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('analysis_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('team_request_id')->nullable();
            $table->unsignedInteger('client_id');
            $table->enum('type', ['report', 'analysis', 'ratio_review', 'budget', 'forecast', 'consolidation', 'other'])->default('report');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('recurrence', ['none', 'monthly', 'quarterly'])->default('none');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('status', ['new', 'in_progress', 'review', 'delivered', 'closed'])->default('new');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->string('deliverable_file')->nullable();
            $table->string('deliverable_link')->nullable();
            $table->unsignedTinyInteger('client_rating')->nullable();
            $table->text('client_feedback')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('client_id');
            $table->index('assigned_to');
            $table->index('parent_id');
        });

        Schema::create('analysis_request_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('analysis_request_id');
            $table->foreign('analysis_request_id')->references('id')->on('analysis_requests')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedInteger('user_id');
            $table->text('comment');
            $table->string('file')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('analysis_request_comments');
        Schema::dropIfExists('analysis_requests');
        Schema::dropIfExists('team_request_status_logs');
        Schema::dropIfExists('team_request_members');
        Schema::dropIfExists('team_requests');
        Schema::dropIfExists('specialist_profiles');
    }

};
