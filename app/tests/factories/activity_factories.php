<?php

$factory('Activity', 'Activity_pos', function($faker) {
    /** @var $faker \Faker\Generator */
    $activity = Activity::pos();

    $activity = array_merge([
        'activity_name' => 'random_activity',
        'activity_name_long' => $faker->words(),
        'user_id' => 'factory:User',
        'user_email' => $faker->email,
        'full_name'  => $faker->name
    ], $activity->getAttributes());

    return $activity;
});
