<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * FAQs and onboarding slides are static copy inside the mobile app, and the
 * admin activity log was dropped from the panel, so none of these tables are
 * read any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('onboarding_slides');
        Schema::dropIfExists('admin_activity_logs');
    }

    public function down(): void
    {
        // These tables are no longer part of the schema.
    }
};
