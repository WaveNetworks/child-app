<?php
/**
 * onboardingActions.php
 * Post-registration onboarding profile collection.
 */

if (($_POST['action'] ?? '') === 'save_onboarding') {
    $errs = [];
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shard_id'];

    if (!$uid) { $errs['auth'] = 'Login required.'; }

    // Step 1 fields
    $life_stage = trim($_POST['life_stage'] ?? '');
    $age_range = trim($_POST['age_range'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $household_role = trim($_POST['household_role'] ?? '');

    // Step 2 fields
    $business_tenure = trim($_POST['business_tenure'] ?? '');
    $prior_ds_experience = trim($_POST['prior_ds_experience'] ?? '');
    $team_size = trim($_POST['team_size'] ?? '');
    $engagement_level = trim($_POST['engagement_level'] ?? '');
    $product_relationship = trim($_POST['product_relationship'] ?? '');

    // Step 3 fields
    $primary_motivation = trim($_POST['primary_motivation'] ?? '');
    $secondary_motivations = $_POST['secondary_motivations'] ?? [];
    $driving_circumstance = trim($_POST['driving_circumstance'] ?? '');

    if (count($errs) <= 0) {
        $profile = [
            'lifeStage' => $life_stage,
            'ageRange' => $age_range,
            'industry' => $industry,
            'householdRole' => $household_role,
            'businessTenure' => $business_tenure,
            'priorDsExperience' => $prior_ds_experience,
            'teamSize' => $team_size,
            'engagementLevel' => $engagement_level,
            'productRelationship' => $product_relationship,
            'primaryMotivation' => $primary_motivation,
            'secondaryMotivations' => $secondary_motivations,
            'drivingCircumstance' => $driving_circumstance,
            'completedAt' => date('c'),
        ];

        // Ensure seller role
        ensure_seller_role($uid, $sid);

        // Create or update instance with profile
        $instance = get_user_instance($uid, $sid, 'ai-assessment');
        if ($instance) {
            $inst_data = $instance['instance_data'];
            $inst_data['subjectProfile'] = $profile;
            update_instance_json($uid, $sid, 'ai-assessment', $inst_data);
        } else {
            create_instance_shell($uid, $sid, $profile, 'ai-assessment');
        }

        // Check for pending invite
        if (!empty($_SESSION['pending_invite'])) {
            $token = $_SESSION['pending_invite'];
            $result = redeem_invite_token($token, $uid, $sid);
            unset($_SESSION['pending_invite']);
            if ($result['valid']) {
                $_SESSION['info'] = 'You are now connected with coach ' . h($result['coach_name']) . '!';
            }
        }

        $_SESSION['success'] = 'Profile saved! Welcome to ElevateHER.';
        log_user_history($uid, $sid, 'onboarding_completed', $profile);
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}
