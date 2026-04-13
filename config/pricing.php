<?php

/**
 * Pricing configuration for subscription plans.
 *
 * These prices are used for MRR (Monthly Recurring Revenue) calculations
 * and should be kept in sync with your actual pricing strategy.
 */
return [
    'subscription_plans' => [
        'free' => 0,
        'starter' => 29,
        'professional' => 99,
        'enterprise' => 299,
    ],
];
