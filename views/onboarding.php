<?php
/**
 * Onboarding — Collect subject profile data
 */
$page_title = 'Welcome to ' . CHILD_APP_NAME;
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$existing = get_onboarding_profile($uid, $sid);
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Tell Us About You</h5>
                <p class="text-muted small mb-0">This helps personalize your <?= h(CHILD_APP_NAME) ?> assessment experience.</p>
            </div>
            <div class="card-body">
                <form method="post" id="onboardingForm">
                    <input type="hidden" name="action" value="save_onboarding">

                    <!-- Step 1: About You -->
                    <h6 class="text-uppercase text-muted small fw-bold mb-3">Step 1: About You</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Life Stage</label>
                            <select class="form-select" name="life_stage">
                                <option value="">Select...</option>
                                <option value="student">Student</option>
                                <option value="early_career">Early Career</option>
                                <option value="mid_career">Mid Career</option>
                                <option value="career_changer">Career Changer</option>
                                <option value="stay_at_home_parent">Stay-at-Home Parent</option>
                                <option value="retiree">Retiree</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age Range</label>
                            <select class="form-select" name="age_range">
                                <option value="">Select...</option>
                                <option value="18-24">18-24</option>
                                <option value="25-34">25-34</option>
                                <option value="35-44">35-44</option>
                                <option value="45-54">45-54</option>
                                <option value="55-64">55-64</option>
                                <option value="65+">65+</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Industry</label>
                            <input type="text" class="form-control" name="industry" placeholder="e.g. Health & Wellness">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Household Role</label>
                            <select class="form-select" name="household_role">
                                <option value="">Select...</option>
                                <option value="primary_earner">Primary Earner</option>
                                <option value="secondary_earner">Secondary Earner</option>
                                <option value="sole_earner">Sole Earner</option>
                                <option value="supplemental">Supplemental Income</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 2: Your Business -->
                    <h6 class="text-uppercase text-muted small fw-bold mb-3">Step 2: Your Business</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Business Tenure</label>
                            <select class="form-select" name="business_tenure">
                                <option value="">Select...</option>
                                <option value="considering">Considering / Not Started</option>
                                <option value="0-6months">0-6 Months</option>
                                <option value="6-12months">6-12 Months</option>
                                <option value="1-2years">1-2 Years</option>
                                <option value="2-5years">2-5 Years</option>
                                <option value="5+years">5+ Years</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prior Direct Sales Experience</label>
                            <select class="form-select" name="prior_ds_experience">
                                <option value="">Select...</option>
                                <option value="none">None</option>
                                <option value="some">Some (tried before)</option>
                                <option value="experienced">Experienced (multiple companies)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Team Size</label>
                            <select class="form-select" name="team_size">
                                <option value="">Select...</option>
                                <option value="none">No team yet</option>
                                <option value="1-5">1-5 people</option>
                                <option value="6-20">6-20 people</option>
                                <option value="21-50">21-50 people</option>
                                <option value="50+">50+ people</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Engagement Level</label>
                            <select class="form-select" name="engagement_level">
                                <option value="">Select...</option>
                                <option value="part_time_casual">Part-time casual</option>
                                <option value="part_time_serious">Part-time serious</option>
                                <option value="full_time">Full-time</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product Relationship</label>
                            <select class="form-select" name="product_relationship">
                                <option value="">Select...</option>
                                <option value="customer_first">Started as a customer</option>
                                <option value="opportunity_first">Attracted by opportunity</option>
                                <option value="both">Both equally</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 3: Your Why -->
                    <h6 class="text-uppercase text-muted small fw-bold mb-3">Step 3: Your Why</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Primary Motivation</label>
                            <select class="form-select" name="primary_motivation">
                                <option value="">Select...</option>
                                <option value="financial_freedom">Financial Freedom</option>
                                <option value="personal_growth">Personal Growth</option>
                                <option value="community">Community & Connection</option>
                                <option value="flexibility">Time Flexibility</option>
                                <option value="helping_others">Helping Others</option>
                                <option value="product_passion">Product Passion</option>
                                <option value="legacy">Building a Legacy</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secondary Motivations</label>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="secondary_motivations[]" value="financial_freedom"><label class="form-check-label small">Financial Freedom</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="secondary_motivations[]" value="personal_growth"><label class="form-check-label small">Personal Growth</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="secondary_motivations[]" value="community"><label class="form-check-label small">Community</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="secondary_motivations[]" value="flexibility"><label class="form-check-label small">Flexibility</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="secondary_motivations[]" value="helping_others"><label class="form-check-label small">Helping Others</label></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Driving Circumstance</label>
                            <textarea class="form-control" name="driving_circumstance" rows="3" placeholder="What's driving you to pursue direct sales right now?"></textarea>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg me-2"></i>Save & Continue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
