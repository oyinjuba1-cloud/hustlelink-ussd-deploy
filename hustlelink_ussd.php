<?php
/**
 * HustleLink USSD Application
 * Gateway: Africa's Talking
 * Endpoint: POST /ussd
 *
 * Africa's Talking POSTs these fields on every request:
 *   sessionId   – unique per dial session
 *   serviceCode – your USSD code e.g. *384*123#
 *   phoneNumber – caller's MSISDN
 *   text        – accumulated input separated by "*"
 */

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Send a CON response (session continues – user sees a menu).
 */
function con(string $message): void
{
    header('Content-Type: text/plain');
    echo 'CON ' . $message;
    exit;
}

/**
 * Send an END response (session terminates – user sees a message).
 */
function ussd_end(string $message): void
{
    header('Content-Type: text/plain');
    echo 'END ' . $message;
    exit;
}

/**
 * Split Africa's Talking accumulated `text` into an array of steps.
 * e.g. "1*2*John" → ["1", "2", "John"]
 */
function steps(string $text): array
{
    if ($text === '') return [];
    return explode('*', $text);
}

// ─── Input ───────────────────────────────────────────────────────────────────

$sessionId   = $_POST['sessionId']   ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$text        = $_POST['text']        ?? '';

$steps = steps($text);
$level = count($steps);   // 0 = fresh dial, 1 = first input, etc.

// ─── Role detection ──────────────────────────────────────────────────────────
// Step 0 → HOME MENU
// Step 1 → role (1=Artisan, 2=Customer)
// Step 2+ → role-specific sub-menu

$role = $steps[1] ?? null;   // "1" = Artisan, "2" = Customer

// ════════════════════════════════════════════════════════════════════════════
// HOME MENU  (fresh dial)
// ════════════════════════════════════════════════════════════════════════════
if ($level === 0) {
    con(
        "Welcome to HustleLink\n" .
        "Connect. Work. Earn.\n\n" .
        "1. I am an Artisan\n" .
        "2. I am a Customer\n" .
        "0. Exit"
    );
}

// Exit from home
if ($level === 1 && $steps[0] === '0') {
    ussd_end("Thank you for using HustleLink. Goodbye!");
}

// ════════════════════════════════════════════════════════════════════════════
// ARTISAN FLOW  (role = "1")
// ════════════════════════════════════════════════════════════════════════════
if ($steps[0] === '1') {

    // Artisan main menu
    if ($level === 1) {
        con(
            "Artisan Menu\n\n" .
            "1. Register / Update Profile\n" .
            "2. Browse Available Jobs\n" .
            "3. View My Accepted Jobs\n" .
            "0. Back"
        );
    }

    $artisanChoice = $steps[1] ?? '';

    // ── 1.1  REGISTER / UPDATE PROFILE ──────────────────────────────────────
    if ($artisanChoice === '1') {

        if ($level === 2) {
            con("Enter your full name:");
        }

        if ($level === 3) {
            con("Enter your skill\n(e.g. Plumber, Electrician,\nTailor, Carpenter):");
        }

        if ($level === 4) {
            con("Enter your location\n(Town / Area):");
        }

        if ($level === 5) {
            con("Enter your years of experience\n(e.g. 3):");
        }

        if ($level === 6) {
            // All profile data collected
            $name       = $steps[2];
            $skill      = $steps[3];
            $location   = $steps[4];
            $experience = $steps[5];

            /*
             * TODO: Persist to database
             * INSERT INTO artisans (phone, name, skill, location, experience)
             * VALUES (?, ?, ?, ?, ?)
             * ON DUPLICATE KEY UPDATE name=?, skill=?, location=?, experience=?
             */

            ussd_end(
                "Profile saved!\n\n" .
                "Name: $name\n" .
                "Skill: $skill\n" .
                "Location: $location\n" .
                "Experience: {$experience} yr(s)\n\n" .
                "Customers can now find you on HustleLink."
            );
        }
    }

    // ── 1.2  BROWSE AVAILABLE JOBS ──────────────────────────────────────────
    if ($artisanChoice === '2') {

        if ($level === 2) {
            con("Filter jobs by skill:\n\n" .
                "1. Plumbing\n" .
                "2. Electrical\n" .
                "3. Tailoring\n" .
                "4. Carpentry\n" .
                "5. Cleaning\n" .
                "6. All Jobs\n" .
                "0. Back");
        }

        if ($level === 3) {
            $skillFilter = $steps[2];

            $skillMap = [
                '1' => 'Plumbing',
                '2' => 'Electrical',
                '3' => 'Tailoring',
                '4' => 'Carpentry',
                '5' => 'Cleaning',
                '6' => 'All',
            ];

            if (!isset($skillMap[$skillFilter])) {
                ussd_end("Invalid option. Please dial again.");
            }

            $selectedSkill = $skillMap[$skillFilter];

            /*
             * TODO: Query database
             * SELECT id, title, location, budget FROM jobs
             * WHERE status='open' AND (skill=? OR ?='All')
             * LIMIT 5
             *
             * For now we show mock data:
             */
            $jobs = [
                ['id' => 101, 'title' => 'Fix leaking pipe',     'location' => 'Lagos Island', 'budget' => '5,000'],
                ['id' => 102, 'title' => 'Wire new apartment',   'location' => 'Ikeja',        'budget' => '15,000'],
                ['id' => 103, 'title' => 'Sew 3 ankara dresses', 'location' => 'Surulere',     'budget' => '8,000'],
            ];

            $menu = "Available Jobs ($selectedSkill):\n\n";
            foreach ($jobs as $i => $job) {
                $n = $i + 1;
                $menu .= "$n. {$job['title']}\n   {$job['location']} | ₦{$job['budget']}\n";
            }
            $menu .= "\nEnter number to accept a job\n0. Back";

            con($menu);
        }

        // Artisan picks a job to accept
        if ($level === 4) {
            $jobChoice = (int)$steps[3];

            // Validate range (1–3 for mock data)
            if ($jobChoice < 1 || $jobChoice > 3) {
                ussd_end("Invalid selection. Dial again to retry.");
            }

            $jobId = 100 + $jobChoice;  // mock ID

            /*
             * TODO: Database write
             * INSERT INTO job_acceptances (job_id, artisan_phone, accepted_at)
             * VALUES (?, ?, NOW())
             *
             * UPDATE jobs SET status='assigned' WHERE id=?
             *
             * Also: notify customer via AT SMS
             * $sms->send(customerPhone, "An artisan has accepted your job! They will contact you shortly.");
             */

            ussd_end(
                "Job #$jobId accepted!\n\n" .
                "The customer will be notified.\n" .
                "They will call you on $phoneNumber.\n\n" .
                "Good luck! - HustleLink"
            );
        }
    }

    // ── 1.3  VIEW MY ACCEPTED JOBS ──────────────────────────────────────────
    if ($artisanChoice === '3') {

        if ($level === 2) {
            /*
             * TODO: Query database
             * SELECT j.id, j.title, j.location, j.status
             * FROM jobs j JOIN job_acceptances a ON a.job_id = j.id
             * WHERE a.artisan_phone = ?
             * ORDER BY a.accepted_at DESC LIMIT 5
             *
             * Mock data below:
             */
            $myJobs = [
                ['id' => 101, 'title' => 'Fix leaking pipe', 'location' => 'Lagos Island', 'status' => 'In Progress'],
                ['id' => 98,  'title' => 'Paint bedroom',    'location' => 'Yaba',         'status' => 'Completed'],
            ];

            if (empty($myJobs)) {
                ussd_end("You have not accepted any jobs yet.\nDial back to browse available jobs.");
            }

            $menu = "My Jobs:\n\n";
            foreach ($myJobs as $job) {
                $menu .= "#{$job['id']} {$job['title']}\n";
                $menu .= "   {$job['location']} | {$job['status']}\n";
            }

            ussd_end($menu);
        }
    }

    // ── Back from artisan menu ───────────────────────────────────────────────
    if ($artisanChoice === '0') {
        con(
            "Welcome to HustleLink\n" .
            "Connect. Work. Earn.\n\n" .
            "1. I am an Artisan\n" .
            "2. I am a Customer\n" .
            "0. Exit"
        );
    }

    // Catch-all invalid artisan input
    if ($level >= 2 && !in_array($artisanChoice, ['1', '2', '3', '0'])) {
        ussd_end("Invalid option. Please dial again.");
    }
}

// ════════════════════════════════════════════════════════════════════════════
// CUSTOMER FLOW  (role = "2")
// ════════════════════════════════════════════════════════════════════════════
if ($steps[0] === '2') {

    // Customer main menu
    if ($level === 1) {
        con(
            "Customer Menu\n\n" .
            "1. Post a Job Request\n" .
            "2. Search Artisans by Skill\n" .
            "3. View My Posted Jobs\n" .
            "0. Back"
        );
    }

    $customerChoice = $steps[1] ?? '';

    // ── 2.1  POST A JOB REQUEST ──────────────────────────────────────────────
    if ($customerChoice === '1') {

        if ($level === 2) {
            con("Select skill needed:\n\n" .
                "1. Plumbing\n" .
                "2. Electrical\n" .
                "3. Tailoring\n" .
                "4. Carpentry\n" .
                "5. Cleaning\n" .
                "6. Other");
        }

        if ($level === 3) {
            con("Describe the job briefly\n(e.g. Fix leaking kitchen pipe):");
        }

        if ($level === 4) {
            con("Enter your location\n(Town / Area):");
        }

        if ($level === 5) {
            con("Enter your budget in Naira\n(e.g. 5000):");
        }

        if ($level === 6) {
            $skillCode  = $steps[2];
            $jobDesc    = $steps[3];
            $location   = $steps[4];
            $budget     = $steps[5];

            $skillMap = [
                '1' => 'Plumbing', '2' => 'Electrical',
                '3' => 'Tailoring', '4' => 'Carpentry',
                '5' => 'Cleaning', '6' => 'Other',
            ];
            $skill = $skillMap[$skillCode] ?? 'Other';

            /*
             * TODO: Persist to database
             * INSERT INTO jobs (customer_phone, skill, description, location, budget, status, created_at)
             * VALUES (?, ?, ?, ?, ?, 'open', NOW())
             *
             * Also: broadcast SMS to matching artisans
             * SELECT phone FROM artisans WHERE skill = ?
             * $sms->sendBulk($artisanPhones, "New job near you: $jobDesc in $location. Dial *384*123# to accept.");
             */

            ussd_end(
                "Job posted!\n\n" .
                "Skill: $skill\n" .
                "Job: $jobDesc\n" .
                "Location: $location\n" .
                "Budget: ₦$budget\n\n" .
                "Nearby artisans have been\nnotified. Expect a call soon!"
            );
        }
    }

    // ── 2.2  SEARCH ARTISANS BY SKILL ────────────────────────────────────────
    if ($customerChoice === '2') {

        if ($level === 2) {
            con("Search artisans by skill:\n\n" .
                "1. Plumbing\n" .
                "2. Electrical\n" .
                "3. Tailoring\n" .
                "4. Carpentry\n" .
                "5. Cleaning\n" .
                "6. Other");
        }

        if ($level === 3) {
            con("Enter your location\n(Town / Area) to find\nnearby artisans:");
        }

        if ($level === 4) {
            $skillCode = $steps[2];
            $location  = $steps[3];

            $skillMap = [
                '1' => 'Plumbing', '2' => 'Electrical',
                '3' => 'Tailoring', '4' => 'Carpentry',
                '5' => 'Cleaning', '6' => 'Other',
            ];
            $skill = $skillMap[$skillCode] ?? 'Other';

            /*
             * TODO: Query database
             * SELECT name, phone, experience FROM artisans
             * WHERE skill = ? AND LOWER(location) LIKE LOWER(?)
             * LIMIT 3
             *
             * Mock data:
             */
            $artisans = [
                ['name' => 'Emeka Obi',    'phone' => '0801***4321', 'exp' => 5],
                ['name' => 'Fatima Bello', 'phone' => '0703***8890', 'exp' => 3],
            ];

            if (empty($artisans)) {
                ussd_end("No $skill artisans found\nnear $location.\n\nTry a different location\nor post a job request.");
            }

            $result = "$skill Artisans near $location:\n\n";
            foreach ($artisans as $a) {
                $result .= "{$a['name']}\n";
                $result .= "  Tel: {$a['phone']} | {$a['exp']}yr(s)\n";
            }
            $result .= "\nCall them directly to hire.";

            ussd_end($result);
        }
    }

    // ── 2.3  VIEW MY POSTED JOBS ─────────────────────────────────────────────
    if ($customerChoice === '3') {

        if ($level === 2) {
            /*
             * TODO: Query database
             * SELECT id, description, skill, status FROM jobs
             * WHERE customer_phone = ?
             * ORDER BY created_at DESC LIMIT 5
             *
             * Mock data:
             */
            $myJobs = [
                ['id' => 201, 'description' => 'Fix leaking pipe', 'skill' => 'Plumbing',   'status' => 'Assigned'],
                ['id' => 198, 'description' => 'Sew 2 shirts',     'skill' => 'Tailoring',  'status' => 'Open'],
            ];

            if (empty($myJobs)) {
                ussd_end("You have no posted jobs yet.\nDial back to post a job.");
            }

            $menu = "My Posted Jobs:\n\n";
            foreach ($myJobs as $job) {
                $menu .= "#{$job['id']} {$job['description']}\n";
                $menu .= "   {$job['skill']} | {$job['status']}\n";
            }

            ussd_end($menu);
        }
    }

    // ── Back from customer menu ──────────────────────────────────────────────
    if ($customerChoice === '0') {
        con(
            "Welcome to HustleLink\n" .
            "Connect. Work. Earn.\n\n" .
            "1. I am an Artisan\n" .
            "2. I am a Customer\n" .
            "0. Exit"
        );
    }

    // Catch-all invalid customer input
    if ($level >= 2 && !in_array($customerChoice, ['1', '2', '3', '0'])) {
        ussd_end("Invalid option. Please dial again.");
    }
}

// ════════════════════════════════════════════════════════════════════════════
// CATCH-ALL – invalid home menu input
// ════════════════════════════════════════════════════════════════════════════
ussd_end("Invalid option. Please dial again.");
