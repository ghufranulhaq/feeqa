<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // FR-002-01. Auto-filled from `name` on create (Business model)
            // when not given explicitly, so every row always has one.
            $table->string('slug')->unique()->after('name');
            $table->string('primary_domain')->nullable()->unique()->after('slug');
            $table->json('additional_domains')->nullable()->after('primary_domain');
            $table->string('country', 2)->nullable()->after('additional_domains');
            // FR-002-01 lists unclaimed/claimed/suspended/closed as the
            // settled states; FR-002-10 adds the transient "pending" state
            // a newly-created business sits in before its automated check
            // passes and it becomes searchable.
            $table->string('status')->default('unclaimed')->after('country');
            $table->timestamp('claimed_at')->nullable()->after('status');

            // FR-002-03 editable fields.
            $table->text('description')->nullable()->after('claimed_at');
            $table->string('website')->nullable()->after('description');
            $table->string('email')->nullable()->after('website');
            $table->string('phone')->nullable()->after('email');
            $table->json('address')->nullable()->after('phone');
            $table->json('social_links')->nullable()->after('address');
            $table->string('logo_path')->nullable()->after('social_links');

            $table->foreignId('primary_category_id')->nullable()->after('logo_path')
                ->constrained('categories')->nullOnDelete();

            // FR-002-25.
            $table->string('employee_size_band')->default('unknown')->after('primary_category_id');

            // FR-002-24: recorded on every seeded/imported profile.
            $table->string('data_source')->nullable()->after('employee_size_band');
            $table->string('import_batch')->nullable()->after('data_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_category_id');
            $table->dropColumn([
                'slug',
                'primary_domain',
                'additional_domains',
                'country',
                'status',
                'claimed_at',
                'description',
                'website',
                'email',
                'phone',
                'address',
                'social_links',
                'logo_path',
                'employee_size_band',
                'data_source',
                'import_batch',
            ]);
        });
    }
};
