<?php

return [
    'menu' => 'Specialists',
    'title' => 'Specialists',
    'subtitle' => 'The team of analysts and accountants that can be assigned to client teams and analysis requests.',

    // Filters
    'filter_role' => 'Role',
    'filter_seniority' => 'Seniority',
    'filter_availability' => 'Availability',
    'filter_all' => 'All',
    'filter_reset' => 'Reset',
    'showing' => 'Showing :shown of :total specialists',
    'empty' => 'There are no specialist profiles yet.',
    'empty_filtered' => 'No specialists match the selected filters.',

    // Card
    'hourly_rate' => 'Hourly rate',
    'per_hour' => ':amount/h',
    'capacity' => 'Capacity',
    'hours_week' => ':hours h/week',
    'utilisation' => 'Utilisation',
    'utilisation_hint' => ':hours h of :capacity h per week (:team h in active teams, :analysis open analysis requests)',
    'skills' => 'Skills',
    'languages' => 'Languages',
    'no_skills' => 'No skills added',
    'availability' => [
        'available' => 'Available',
        'busy' => 'Fully booked',
        'unavailable' => 'Unavailable',
    ],
    'edit' => 'Edit profile',

    // Edit modal
    'edit_title' => 'Specialist Profile — :name',
    'role' => 'Role',
    'seniority' => 'Seniority',
    'hourly_rate_label' => 'Hourly rate (EUR)',
    'weekly_capacity' => 'Weekly capacity (hours)',
    'languages_label' => 'Languages',
    'languages_placeholder' => 'e.g. Bulgarian, English, German',
    'skills_label' => 'Skills',
    'skills_placeholder' => 'e.g. Power BI, IFRS, Excel',
    'skills_help' => 'Separate the skills with commas.',
    'is_available' => 'Available for new assignments',
    'save' => 'Save',
    'close' => 'Close',
    'saved' => 'The specialist profile was saved.',

    // AFV (S12): new profile
    'add' => 'Add specialist',
    'add_title' => 'New Specialist Profile',
    'employee' => 'Employee',
    'employee_placeholder' => 'Choose an employee',
    'hourly_rate_help' => 'Used for the estimated price of team requests.',
    'added' => 'The specialist is added.',
    'empty_text' => 'Add the employees who will work in client teams and on analysis requests - with role, rate and capacity.',
];
