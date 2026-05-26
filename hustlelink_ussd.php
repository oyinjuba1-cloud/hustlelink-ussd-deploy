<?php
// ─── Output & Header Configuration ──────────────────────────────────────────
// Prevent accidental notices, warnings, or spaces from corrupting the raw response
ob_start();
header('Content-Type: text/plain');

/**
 * HustleLink USSD Application
 * Gateway: Africa's Talking
 * Endpoint: POST /ussd
 */

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Send a CON response (session continues – user sees a menu).
 */
function con(string $message): void
{
    // Clear any accidental background string buffers up to this point
    ob_clean();
    echo 'CON ' . $message;
    exit;
}

/**
 * Send an END response (session terminates – user sees a message).
 */
function ussd_end(string $message): void
{
    ob_clean();
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

// ─── Input Extraction ───────────────────────────────────────────────────────

$sessionId   = $_POST['sessionId']   ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$text        = $_POST['text']        ?? '';

$rawSteps = steps($text);

// ─── Intelligent Navigation / "Back" Handler ────────────────────────────────
$steps = [];
foreach ($rawSteps as $step) {
    if ($step === '0') {
        // If user hits 0, pop the last selected option off the stack to go back
        array_pop($steps);
    } else {
        $steps[] = $step;
    }
}

$level = count($steps);   // 0 = fresh dial, 1 = first input, etc.

// ─── Centralized Route Controller ───────────────────────────────────────────

if ($level === 0) {
    // ════════════════════════════════════════════════════════════════════════
    // HOME MENU  (Fresh Dial)
    // ════════════════════════════════════════════════════════════════════════
    con(
        "Welcome to HustleLink\n" .
        "Connect. Work. Earn.\n\n" .
        "1. I am an Artisan\n" .
        "2. I am a Customer\n" .
        "0. Exit"
    );

} elseif ($steps[0] === '1') {
    // ════════════════════════════════════════════════════════════════════════
    // ARTISAN FLOW  (role = "1")
    // ════════════════════════════════════════════════════════════════════════
    
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

    // ── 1.1 REGISTER / UPDATE PROFILE ──────────────────────────────────────
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
             * VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=?, skill=?, location=?, experience=?
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
    // ── 1.2 BROWSE AVAILABLE JOBS ──────────────────────────────────────────
    elseif ($artisanChoice === '2') {
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
                '1' => 'Plumbing', '2' => 'Electrical', '3' => 'Tailoring',
                '4' => 'Carpentry', '5' => 'Cleaning', '6' => 'All'
            ];

            if (!isset($skillMap[$skillFilter])) {
                ussd_end("Invalid option. Please dial again.");
            }

            $selectedSkill = $skillMap[$skillFilter];

            // Mock Data Retrieval
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

        if ($level === 4) {
            $jobChoice = (int)$steps[3];
            if ($jobChoice < 1 || $jobChoice > 3) {
                ussd_end("Invalid selection. Dial again to retry.");
            }

            $jobId = 100 + $jobChoice;

            /*
             * TODO: Database write updates
             * UPDATE jobs SET status='assigned' WHERE id=?
             */

            ussd_end(
                "Job #$jobId accepted!\n\n" .
                "The customer will be notified.\n" .
                "They will call you on $phoneNumber.\n\n" .
                "Good luck! - HustleLink"
            );
        }
    }
    // ── 1.3 VIEW MY ACCEPTED JOBS ──────────────────────────────────────────
    elseif ($artisanChoice === '3') {
        if ($level === 2) {
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
    // Deep fallback option for invalid artisan sub-choices
    else {
        ussd_end("Invalid choice inside Artisan Menu. Please redial.");
    }

} elseif ($steps[0] === '2') {
    // ════════════════════════════════════════════════════════════════════════
    // CUSTOMER FLOW  (role = "2")
    // ════════════════════════════════════════════════════════════════════════
    
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

    // ── 2.1 POST A JOB REQUEST ──────────────────────────────────────────────
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
                '1' => 'Plumbing', '2' => 'Electrical', '3' => 'Tailoring',
                '4' => 'Carpentry', '5' => 'Cleaning', '6' => 'Other'
            ];
            $skill = $skillMap[$skillCode] ?? 'Other';

            /*
             * TODO: Insert entry into jobs database table.
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
    // ── 2.2 SEARCH ARTISANS BY SKILL ────────────────────────────────────────
    elseif ($customerChoice === '2') {
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
                '1' => 'Plumbing', '2' => 'Electrical', '3' => 'Tailoring',
                '4' => 'Carpentry', '5' => 'Cleaning', '6' => 'Other'
            ];
            $skill = $skillMap[$skillCode] ?? 'Other';

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
                $result .= "   Tel: {$a['phone']} | {$a['exp']}yr(s)\n";
            }
            $result .= "\nCall them directly to hire.";

            ussd_end($result);
        }
    }
    // ── 2.3 VIEW MY POSTED JOBS ─────────────────────────────────────────────
    elseif ($customerChoice === '3') {
        if ($level === 2) {
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
    } else {
        ussd_end("Invalid choice inside Customer Menu. Please redial.");
    }

} elseif ($rawSteps[0] === '0') {
    // Handle home screen explicit exit command
    ussd_end("Thank you for using HustleLink. Goodbye!");

} else {
    // ════════════════════════════════════════════════════════════════════════
    // MASTER GLOBAL CATCH-ALL
    // ════════════════════════════════════════════════════════════════════════
    ussd_end("Invalid application entry option. Please dial again.");
}

// Flush response payload out safely
ob_end_flush();
