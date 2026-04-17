<?php
/**
 * Post Generator — triggered by cron-job.org (HTTP) or CLI (--local flag)
 *
 * HTTP usage:  https://yourdomain.com/generate.php?token=YOUR_CRON_TOKEN
 * CLI usage:   php generate.php --local
 * CLI force:   php generate.php --local --force
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$is_cli = (php_sapi_name() === 'cli');

// ─── Auth ─────────────────────────────────────────────────────────────────────
if (!$is_cli) {
    $token = $_GET['token'] ?? '';
    if (!hash_equals(CRON_TOKEN, $token)) {
        http_response_code(403);
        die('Forbidden');
    }
}

// ─── Schedule Guard ───────────────────────────────────────────────────────────
$force = $is_cli && in_array('--force', $argv ?? []);
if (!$force) {
    if (!should_run()) {
        $msg = 'Too soon — skipping generation.';
        log_generation('skipped', null, $msg);
        die($msg . PHP_EOL);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TOPIC POOL — 10 Framework Themes × 3-5 Subtopics, mapped to 7 blog categories
// Categories: ai-implementation | compliance | cyber-risk | frameworks |
//             hipaa | privacy | ransomware
// ═══════════════════════════════════════════════════════════════════════════════
$topic_pool = [

    // ── Theme 1: The "Care vs. Security" Budget Conflict ──────────────────────
    [
        'title'    => 'How to Quantify Cybersecurity ROI for Hospital Boards Using the FAIR Model',
        'category' => 'cyber-risk',
        'theme'    => 'budget-roi',
    ],
    [
        'title'    => 'HHS HICP Tiered Security Recommendations: A Prioritized Roadmap for Resource-Constrained Hospitals',
        'category' => 'compliance',
        'theme'    => 'budget-roi',
    ],
    [
        'title'    => 'CIS Controls v8 IG1: Essential Cyber Hygiene for Small and Critical Access Hospitals',
        'category' => 'frameworks',
        'theme'    => 'budget-roi',
    ],
    [
        'title'    => 'Building a Cybersecurity Risk Register That Converts to Hospital Capital Budget Approvals',
        'category' => 'cyber-risk',
        'theme'    => 'budget-roi',
    ],
    [
        'title'    => 'HHS Free SRA Tool: HIPAA Risk Assessments Without Expensive Consultants',
        'category' => 'hipaa',
        'theme'    => 'budget-roi',
    ],

    // ── Theme 2: Physician Resistance to Security Friction ────────────────────
    [
        'title'    => 'NIST SP 800-63B: Modernizing Clinical Authentication to Eliminate Password Fatigue',
        'category' => 'frameworks',
        'theme'    => 'clinical-friction',
    ],
    [
        'title'    => 'Badge Tap and Single Sign-On Authentication for Hospital Shared Workstations',
        'category' => 'cyber-risk',
        'theme'    => 'clinical-friction',
    ],
    [
        'title'    => 'FIDO2 Passkeys in Healthcare: Phishing-Resistant MFA Without the Workflow Penalty',
        'category' => 'cyber-risk',
        'theme'    => 'clinical-friction',
    ],
    [
        'title'    => 'Zero Trust Adaptive Authentication: Risk-Based MFA That Stays Invisible in Normal Clinical Flow',
        'category' => 'frameworks',
        'theme'    => 'clinical-friction',
    ],
    [
        'title'    => 'ADKAR Change Management for Security Tool Adoption in Clinical Settings',
        'category' => 'compliance',
        'theme'    => 'clinical-friction',
    ],

    // ── Theme 3: Shadow AI and Shadow IT ──────────────────────────────────────
    [
        'title'    => 'Shadow AI in Healthcare: How to Govern the Tools You Cannot See',
        'category' => 'ai-implementation',
        'theme'    => 'shadow-ai',
    ],
    [
        'title'    => 'NIST AI RMF GOVERN Function: Building an AI Inventory to Root Out Unauthorized Tools',
        'category' => 'ai-implementation',
        'theme'    => 'shadow-ai',
    ],
    [
        'title'    => 'ISO 42001 AI Management Systems: A Healthcare Implementation Primer',
        'category' => 'compliance',
        'theme'    => 'shadow-ai',
    ],
    [
        'title'    => 'Cloud Access Security Brokers (CASB) for Healthcare: Real-Time Visibility Into Shadow SaaS and AI',
        'category' => 'cyber-risk',
        'theme'    => 'shadow-ai',
    ],
    [
        'title'    => 'Tiered AI Acceptable Use Policies: Fast-Track Approval to Stop Clinical Staff Going Rogue',
        'category' => 'compliance',
        'theme'    => 'shadow-ai',
    ],

    // ── Theme 4: The "Frankenstein" Legacy Technology Problem ─────────────────
    [
        'title'    => 'Network Micro-Segmentation for Legacy Medical Systems That Cannot Be Patched',
        'category' => 'cyber-risk',
        'theme'    => 'legacy-tech',
    ],
    [
        'title'    => 'Application Allowlisting on End-of-Life Clinical Systems: NIST SP 800-167 in Practice',
        'category' => 'frameworks',
        'theme'    => 'legacy-tech',
    ],
    [
        'title'    => 'CISA Known Exploited Vulnerabilities Catalog: Prioritizing Patches in Under-Resourced Healthcare IT',
        'category' => 'cyber-risk',
        'theme'    => 'legacy-tech',
    ],
    [
        'title'    => 'Staged EHR Modernization: Building a Risk Register That Makes the Business Case for Replacement',
        'category' => 'cyber-risk',
        'theme'    => 'legacy-tech',
    ],
    [
        'title'    => 'Compensating Controls for Unpatchable Legacy Clinical Workstations: A Practitioner\'s Guide',
        'category' => 'compliance',
        'theme'    => 'legacy-tech',
    ],

    // ── Theme 5: Vendor Lock-in and Unpatchable Medical Devices ──────────────
    [
        'title'    => 'FDA Section 524B Cybersecurity Requirements: What the 2023 Omnibus Means for Device Procurement',
        'category' => 'compliance',
        'theme'    => 'medical-devices',
    ],
    [
        'title'    => 'MDS2 Questionnaires: Evaluating Vendor Security Before Medical Device Purchase',
        'category' => 'compliance',
        'theme'    => 'medical-devices',
    ],
    [
        'title'    => 'NIST NCCoE SP 1800-8: Securing IoMT Devices Through Network Isolation and TLS Encryption',
        'category' => 'frameworks',
        'theme'    => 'medical-devices',
    ],
    [
        'title'    => 'Network Access Control and VLAN Isolation for Hospital Medical Device Security',
        'category' => 'cyber-risk',
        'theme'    => 'medical-devices',
    ],
    [
        'title'    => 'SBOM-Based Vendor Contracts: Holding Medical Device Manufacturers Accountable for Component Risk',
        'category' => 'compliance',
        'theme'    => 'medical-devices',
    ],

    // ── Theme 6: Chronic Understaffing and Professional Burnout ──────────────
    [
        'title'    => 'vCISO and MSSP Models: Enterprise-Grade Security Leadership for Small and Rural Hospitals',
        'category' => 'cyber-risk',
        'theme'    => 'staffing',
    ],
    [
        'title'    => 'NICE Cybersecurity Workforce Framework: Building Internal Security Talent Pipelines in Healthcare',
        'category' => 'frameworks',
        'theme'    => 'staffing',
    ],
    [
        'title'    => 'SOAR Platforms in Healthcare SOCs: Automating Tier-1 Alert Triage to Defeat Analyst Burnout',
        'category' => 'cyber-risk',
        'theme'    => 'staffing',
    ],
    [
        'title'    => 'H-ISAC Threat Intelligence Sharing: Community-Sourced Security for Understaffed Teams',
        'category' => 'frameworks',
        'theme'    => 'staffing',
    ],
    [
        'title'    => 'Security Champions Programs: Distributing Cyber Responsibility Across Clinical Departments',
        'category' => 'compliance',
        'theme'    => 'staffing',
    ],

    // ── Theme 7: The "Compliance Labyrinth" ───────────────────────────────────
    [
        'title'    => 'HITRUST CSF r2: One Assessment to Map HIPAA, NIST CSF, ISO 27001, and SOC 2 Simultaneously',
        'category' => 'frameworks',
        'theme'    => 'compliance-labyrinth',
    ],
    [
        'title'    => 'NIST SP 800-66r2: The 2024 Update to Implementing the HIPAA Security Rule',
        'category' => 'hipaa',
        'theme'    => 'compliance-labyrinth',
    ],
    [
        'title'    => 'Privacy by Design for Healthcare Application Developers: ISO 29101 in Practice',
        'category' => 'privacy',
        'theme'    => 'compliance-labyrinth',
    ],
    [
        'title'    => 'FedRAMP-Authorized Healthcare Cloud: Eliminating the BAA Negotiation Bottleneck',
        'category' => 'compliance',
        'theme'    => 'compliance-labyrinth',
    ],
    [
        'title'    => 'HHS OCR Audit Protocol: Building a Defensible HIPAA Compliance Trail That Reduces Penalty Exposure',
        'category' => 'hipaa',
        'theme'    => 'compliance-labyrinth',
    ],

    // ── Theme 8: The "Magic AI" vs. Broken Workflow Problem ──────────────────
    [
        'title'    => 'Lean Six Sigma Before AI: Why Process Mapping Must Precede Automation in Healthcare',
        'category' => 'ai-implementation',
        'theme'    => 'ai-workflows',
    ],
    [
        'title'    => 'NIST AI RMF MAP Function: Assessing Organizational Readiness Before Clinical AI Deployment',
        'category' => 'ai-implementation',
        'theme'    => 'ai-workflows',
    ],
    [
        'title'    => 'Kotter\'s 8-Step Model for Clinical AI Adoption: Managing the "Magic AI" Expectation Gap',
        'category' => 'ai-implementation',
        'theme'    => 'ai-workflows',
    ],
    [
        'title'    => 'Process Mining Before AI: Empirically Mapping Clinical Workflows Before Automation',
        'category' => 'ai-implementation',
        'theme'    => 'ai-workflows',
    ],
    [
        'title'    => 'Organizational Readiness Assessments for EHR and Clinical AI Integration Projects',
        'category' => 'ai-implementation',
        'theme'    => 'ai-workflows',
    ],

    // ── Theme 9: Security Fatigue and "Sticky Stalagmites" ────────────────────
    [
        'title'    => 'Fogg Behavior Model Applied to Healthcare Security: Reducing Friction to Raise Compliance',
        'category' => 'cyber-risk',
        'theme'    => 'security-fatigue',
    ],
    [
        'title'    => 'Just-in-Time Privileged Access Management: Eliminating Shared Credentials in Clinical Settings',
        'category' => 'cyber-risk',
        'theme'    => 'security-fatigue',
    ],
    [
        'title'    => 'SANS Security Awareness Maturity Model: Measuring Security Culture Instead of Assuming It',
        'category' => 'compliance',
        'theme'    => 'security-fatigue',
    ],
    [
        'title'    => 'Nudge Architecture for Healthcare IT: Making the Secure Option the Easiest Option',
        'category' => 'cyber-risk',
        'theme'    => 'security-fatigue',
    ],
    [
        'title'    => 'COM-B Behavioral Change Model: Why Clinical Staff Bypass Security Controls and What to Do About It',
        'category' => 'cyber-risk',
        'theme'    => 'security-fatigue',
    ],

    // ── Theme 10: Disconnected and "Incompetent" Leadership ──────────────────
    [
        'title'    => 'NACD Director\'s Handbook on Cyber-Risk Oversight: Five Principles Every Health System Board Needs',
        'category' => 'frameworks',
        'theme'    => 'leadership',
    ],
    [
        'title'    => 'NIST CSF 2.0 GOVERN Function: A New Accountability Framework for Healthcare Cybersecurity Leaders',
        'category' => 'frameworks',
        'theme'    => 'leadership',
    ],
    [
        'title'    => 'FAIR Model for Board-Level Presentations: Translating Ransomware Exposure into Dollar-Denominated Risk',
        'category' => 'cyber-risk',
        'theme'    => 'leadership',
    ],
    [
        'title'    => 'SEC Cybersecurity Disclosure Rules: Implications for Healthcare Executive and Board Liability',
        'category' => 'compliance',
        'theme'    => 'leadership',
    ],
    [
        'title'    => 'Building a CISO-to-Board Direct Reporting Structure in Health Systems',
        'category' => 'frameworks',
        'theme'    => 'leadership',
    ],

    // ── Direct HIPAA topics ───────────────────────────────────────────────────
    [
        'title'    => 'HIPAA Security Rule Technical Safeguards: A 2025 Implementation Checklist for Health Systems',
        'category' => 'hipaa',
        'theme'    => 'hipaa-direct',
    ],
    [
        'title'    => 'Business Associate Agreements in the Age of Cloud AI: What Covered Entities Must Require',
        'category' => 'hipaa',
        'theme'    => 'hipaa-direct',
    ],
    [
        'title'    => 'HIPAA Breach Notification Rule: Timelines, HHS Obligations, and OCR Enforcement Trends',
        'category' => 'hipaa',
        'theme'    => 'hipaa-direct',
    ],
    [
        'title'    => 'Minimum Necessary Standard Under HIPAA: Limiting PHI Access in Modern Clinical Workflows',
        'category' => 'hipaa',
        'theme'    => 'hipaa-direct',
    ],
    [
        'title'    => 'HIPAA Administrative Safeguards: Workforce Training, Sanction Policies, and Access Management',
        'category' => 'hipaa',
        'theme'    => 'hipaa-direct',
    ],

    // ── Direct Privacy topics ─────────────────────────────────────────────────
    [
        'title'    => 'State Health Privacy Laws Beyond HIPAA: California CMIA and Washington My Health MY Data Act',
        'category' => 'privacy',
        'theme'    => 'privacy-direct',
    ],
    [
        'title'    => 'Reproductive Health Data Privacy After Dobbs: Legal Exposure and Risk Mitigation for Providers',
        'category' => 'privacy',
        'theme'    => 'privacy-direct',
    ],
    [
        'title'    => 'Mental Health Data Privacy: 42 CFR Part 2 Modernization and HIPAA Alignment',
        'category' => 'privacy',
        'theme'    => 'privacy-direct',
    ],
    [
        'title'    => 'De-identification vs. Anonymization of Health Data: HIPAA Expert Determination vs. Safe Harbor Method',
        'category' => 'privacy',
        'theme'    => 'privacy-direct',
    ],
    [
        'title'    => 'FTC Health Breach Notification Rule: Obligations for Non-HIPAA Digital Health Apps and Wearables',
        'category' => 'privacy',
        'theme'    => 'privacy-direct',
    ],

    // ── Direct Ransomware topics ──────────────────────────────────────────────
    [
        'title'    => 'Ransomware Response Playbook for Hospitals: Detection, Containment, and Recovery in 72 Hours',
        'category' => 'ransomware',
        'theme'    => 'ransomware-direct',
    ],
    [
        'title'    => 'Healthcare Ransomware Trends 2024-2025: Attack Vectors, Dwell Times, and True Recovery Costs',
        'category' => 'ransomware',
        'theme'    => 'ransomware-direct',
    ],
    [
        'title'    => 'Immutable Backup Architecture for Hospitals: Defeating Ransomware\'s Final Move',
        'category' => 'ransomware',
        'theme'    => 'ransomware-direct',
    ],
    [
        'title'    => 'Ransomware Negotiation and Cyber Insurance: What Health System Executives Need to Know',
        'category' => 'ransomware',
        'theme'    => 'ransomware-direct',
    ],
    [
        'title'    => 'Active Directory Hardening to Stop Ransomware Lateral Movement in Hospital Networks',
        'category' => 'ransomware',
        'theme'    => 'ransomware-direct',
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// BOOK POOL — 25 curated, real titles with category relevance tags
// Books are pre-selected per post; Claude writes the relevance reason.
// ═══════════════════════════════════════════════════════════════════════════════
$book_pool = [
    [
        'title'      => 'Healthcare Cybersecurity',
        'author'     => 'W. Arthur Conklin and Paul Brooks',
        'search'     => 'Healthcare Cybersecurity Conklin Brooks',
        'categories' => ['cyber-risk', 'compliance', 'hipaa', 'frameworks', 'ransomware'],
    ],
    [
        'title'      => 'HIPAA Plain & Simple: A Healthcare Professional\'s Handbook',
        'author'     => 'Carolyn P. Hartley and Erin Dempsey-Clifford',
        'search'     => 'HIPAA Plain Simple Healthcare Professional Handbook Hartley',
        'categories' => ['hipaa', 'compliance', 'privacy'],
    ],
    [
        'title'      => 'Medical Device Cybersecurity for Engineers and Manufacturers',
        'author'     => 'Axel Wirth, Christopher Gates, and Jacob Holling',
        'search'     => 'Medical Device Cybersecurity Engineers Manufacturers Wirth Gates',
        'categories' => ['cyber-risk', 'compliance', 'frameworks'],
    ],
    [
        'title'      => 'How to Measure Anything in Cybersecurity Risk',
        'author'     => 'Douglas W. Hubbard and Richard Seiersen',
        'search'     => 'How to Measure Anything Cybersecurity Risk Hubbard Seiersen',
        'categories' => ['cyber-risk', 'frameworks', 'compliance'],
    ],
    [
        'title'      => 'Implementing the NIST Cybersecurity Framework',
        'author'     => 'David Moskowitz',
        'search'     => 'Implementing NIST Cybersecurity Framework Moskowitz',
        'categories' => ['frameworks', 'compliance', 'cyber-risk'],
    ],
    [
        'title'      => 'Ransomware: Defending Against Digital Extortion',
        'author'     => 'Allan Liska and Timothy Gallo',
        'search'     => 'Ransomware Defending Against Digital Extortion Liska Gallo',
        'categories' => ['ransomware', 'cyber-risk'],
    ],
    [
        'title'      => 'Data Breach Preparation and Response',
        'author'     => 'Kevvie Fowler',
        'search'     => 'Data Breach Preparation Response Kevvie Fowler',
        'categories' => ['cyber-risk', 'ransomware', 'compliance', 'hipaa'],
    ],
    [
        'title'      => 'Threat Modeling: Designing for Security',
        'author'     => 'Adam Shostack',
        'search'     => 'Threat Modeling Designing for Security Adam Shostack',
        'categories' => ['cyber-risk', 'frameworks', 'compliance'],
    ],
    [
        'title'      => 'Zero Trust Networks: Building Secure Systems in Untrusted Networks',
        'author'     => 'Evan Gilman and Doug Barth',
        'search'     => 'Zero Trust Networks Building Secure Systems Gilman Barth',
        'categories' => ['cyber-risk', 'frameworks', 'hipaa'],
    ],
    [
        'title'      => 'The Privacy Engineer\'s Manifesto',
        'author'     => 'Michelle Finneran Dennedy, Jonathan Fox, and Tom Finneran',
        'search'     => 'Privacy Engineer Manifesto Dennedy Fox Finneran',
        'categories' => ['privacy', 'compliance', 'ai-implementation'],
    ],
    [
        'title'      => 'Data Privacy: A Runbook for Engineers',
        'author'     => 'Nishant Bhajaria',
        'search'     => 'Data Privacy Runbook for Engineers Nishant Bhajaria',
        'categories' => ['privacy', 'compliance', 'ai-implementation'],
    ],
    [
        'title'      => 'Weapons of Math Destruction',
        'author'     => 'Cathy O\'Neil',
        'search'     => 'Weapons of Math Destruction Cathy ONeil',
        'categories' => ['ai-implementation', 'compliance', 'privacy'],
    ],
    [
        'title'      => 'The Alignment Problem: Machine Learning and Human Values',
        'author'     => 'Brian Christian',
        'search'     => 'The Alignment Problem Machine Learning Human Values Brian Christian',
        'categories' => ['ai-implementation'],
    ],
    [
        'title'      => 'Trustworthy AI: A Business Guide to Navigating Risks and Building Trust',
        'author'     => 'Beena Ammanath',
        'search'     => 'Trustworthy AI Business Guide Navigating Risks Beena Ammanath',
        'categories' => ['ai-implementation', 'compliance', 'frameworks'],
    ],
    [
        'title'      => 'Competing in the Age of AI: Strategy and Leadership When Algorithms Run the World',
        'author'     => 'Marco Iansiti and Karim R. Lakhani',
        'search'     => 'Competing Age of AI Strategy Leadership Iansiti Lakhani',
        'categories' => ['ai-implementation'],
    ],
    [
        'title'      => 'AI Ethics',
        'author'     => 'Mark Coeckelbergh',
        'search'     => 'AI Ethics Mark Coeckelbergh MIT Press',
        'categories' => ['ai-implementation', 'compliance', 'privacy'],
    ],
    [
        'title'      => 'Social Engineering: The Science of Human Hacking',
        'author'     => 'Christopher Hadnagy',
        'search'     => 'Social Engineering Science of Human Hacking Hadnagy',
        'categories' => ['cyber-risk', 'ransomware'],
    ],
    [
        'title'      => 'Practical Cloud Security: A Guide for Cloud Environments',
        'author'     => 'Chris Dotson',
        'search'     => 'Practical Cloud Security Guide Cloud Environments Dotson',
        'categories' => ['cyber-risk', 'compliance', 'hipaa'],
    ],
    [
        'title'      => 'Project Zero Trust: A Story About a Strategy for Aligning Security and the Business',
        'author'     => 'George Finney',
        'search'     => 'Project Zero Trust Strategy Aligning Security Business George Finney',
        'categories' => ['frameworks', 'cyber-risk'],
    ],
    [
        'title'      => 'NIST Cybersecurity Framework: A Pocket Guide',
        'author'     => 'Alan Calder',
        'search'     => 'NIST Cybersecurity Framework Pocket Guide Alan Calder',
        'categories' => ['frameworks', 'compliance'],
    ],
    [
        'title'      => 'Security Risk Management: Building an Information Security Risk Management Program from the Ground Up',
        'author'     => 'Evan Wheeler',
        'search'     => 'Security Risk Management Information Security Program Evan Wheeler',
        'categories' => ['cyber-risk', 'frameworks', 'compliance'],
    ],
    [
        'title'      => 'Hacking Healthcare: A Guide to Standards, Workflows, and Meaningful Use',
        'author'     => 'Fred Trotter and David Uhlman',
        'search'     => 'Hacking Healthcare Guide Standards Workflows Trotter Uhlman',
        'categories' => ['hipaa', 'compliance', 'ai-implementation'],
    ],
    [
        'title'      => 'Privacy in Practice: Establish and Operationalize a Holistic Data Privacy Program',
        'author'     => 'Alan Tang',
        'search'     => 'Privacy in Practice Holistic Data Privacy Program Alan Tang',
        'categories' => ['privacy', 'compliance', 'hipaa'],
    ],
    [
        'title'      => 'Incident Response & Computer Forensics, Third Edition',
        'author'     => 'Jason Luttgens, Matthew Pepe, and Kevin Mandia',
        'search'     => 'Incident Response Computer Forensics Third Edition Luttgens Mandia',
        'categories' => ['cyber-risk', 'ransomware'],
    ],
    [
        'title'      => 'The Phoenix Project: A Novel About IT, DevOps, and Helping Your Business Win',
        'author'     => 'Gene Kim, Kevin Behr, and George Spafford',
        'search'     => 'The Phoenix Project IT DevOps Gene Kim Behr Spafford',
        'categories' => ['frameworks', 'ai-implementation', 'compliance'],
    ],
];

// ─── Respond immediately to cron-job.org, spawn generation as subprocess ─────
if (!$is_cli) {
    $ack = "202 Accepted — generating post in background.\n";
    http_response_code(202);
    header('Content-Type: text/plain');
    header('Connection: close');
    header('Content-Length: ' . strlen($ack));
    echo $ack;

    // Flush through all buffers + CDN proxy layers
    while (ob_get_level()) ob_end_flush();
    flush();

    // Spawn a completely independent subprocess so Railway's CDN doesn't
    // buffer the response waiting for this script to finish
    $php    = PHP_BINARY;
    $script = escapeshellarg(__FILE__);
    $log    = escapeshellarg(LOG_PATH);
    shell_exec("{$php} {$script} --local >> {$log} 2>&1 &");

    exit; // This process ends — subprocess does the real work
}

// ─── Main (runs in background for HTTP, inline for CLI) ───────────────────────
try {
    $db             = get_db();
    $topic_data     = select_topic($topic_pool);
    $topic          = $topic_data['title'];
    $category_slug  = $topic_data['category'];
    $selected_books = select_books($book_pool, $category_slug);

    log_msg("Generating post for topic: {$topic}");
    log_msg("Category: {$category_slug} | Books: " . implode(', ', array_column($selected_books, 'title')));

    $response  = call_claude($topic, $selected_books);
    $post_data = parse_post_response($response, $selected_books);

    insert_post($db, $post_data, $topic, $category_slug);
    log_generation('success', $topic, 'Post created: ' . $post_data['slug']);
    log_msg("SUCCESS: Post '{$post_data['title']}' created (slug: {$post_data['slug']})");

} catch (Throwable $e) {
    $msg = 'ERROR: ' . $e->getMessage();
    log_generation('error', $topic ?? null, $msg);
    log_msg($msg);
    if ($is_cli) echo $msg . PHP_EOL;
}

// ═══════════════════════════════════════════════════════════════════════════════
// TOPIC SELECTION — Category-balanced round-robin with deduplication
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Select the next topic using a shuffled category queue (ensures all 7 categories
 * appear in every cycle) and excludes the last 30 used topics.
 */
function select_topic(array $pool): array {
    $all_categories = ['ai-implementation', 'compliance', 'cyber-risk',
                       'frameworks', 'hipaa', 'privacy', 'ransomware'];

    // Pop the next target category from the queue; refill when empty
    $queue = json_decode(get_setting('category_queue', '[]'), true);
    if (empty($queue)) {
        $queue = $all_categories;
        shuffle($queue);
        log_msg('Category queue refilled: ' . implode(' → ', $queue));
    }
    $target = array_shift($queue);
    set_setting('category_queue', json_encode(array_values($queue)));

    // Avoid repeating the last 30 topics
    $recent = json_decode(get_setting('recent_topics', '[]'), true);

    // 1st preference: target category, not recently used
    $available = array_values(array_filter($pool, fn($t) =>
        $t['category'] === $target && !in_array($t['title'], $recent)
    ));

    // 2nd preference: any category, not recently used
    if (empty($available)) {
        $available = array_values(array_filter($pool, fn($t) =>
            !in_array($t['title'], $recent)
        ));
    }

    // 3rd preference: reset recents and use target category
    if (empty($available)) {
        $recent    = [];
        $available = array_values(array_filter($pool, fn($t) => $t['category'] === $target));
    }

    // Final fallback: anything
    if (empty($available)) {
        $available = $pool;
    }

    $selected = $available[array_rand($available)];

    // Track as recently used (cap at 30)
    $recent[] = $selected['title'];
    if (count($recent) > 30) {
        $recent = array_slice($recent, -30);
    }
    set_setting('recent_topics', json_encode($recent));

    return $selected;
}

// ═══════════════════════════════════════════════════════════════════════════════
// BOOK SELECTION — Category-filtered, weighted-random, with deduplication
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Pick 3 books from the pool relevant to the post category, avoiding the last
 * 15 used titles so recommendations stay fresh across posts.
 */
function select_books(array $pool, string $category): array {
    $recent = json_decode(get_setting('recent_books', '[]'), true);

    // 1st preference: category match, not recently used
    $available = array_values(array_filter($pool, fn($b) =>
        in_array($category, $b['categories']) && !in_array($b['title'], $recent)
    ));

    // 2nd preference: category match, ignore recency
    if (count($available) < 3) {
        $available = array_values(array_filter($pool, fn($b) =>
            in_array($category, $b['categories'])
        ));
    }

    // 3rd preference: anything not recently used
    if (count($available) < 3) {
        $available = array_values(array_filter($pool, fn($b) =>
            !in_array($b['title'], $recent)
        ));
    }

    // Final fallback: full pool
    if (count($available) < 3) {
        $available = $pool;
    }

    shuffle($available);
    $selected = array_slice($available, 0, 3);

    // Track recently used (cap at 15)
    foreach ($selected as $b) {
        $recent[] = $b['title'];
    }
    if (count($recent) > 15) {
        $recent = array_slice($recent, -15);
    }
    set_setting('recent_books', json_encode($recent));

    return $selected;
}

// ─── Schedule Guard ───────────────────────────────────────────────────────────
function should_run(): bool {
    $db   = get_db();
    $stmt = $db->query(
        "SELECT created_at FROM generation_log WHERE status = 'success' ORDER BY id DESC LIMIT 1"
    );
    $last = $stmt->fetchColumn();
    if (!$last) return true;

    $gap = TEST_MODE ? TEST_INTERVAL_SECS : PROD_INTERVAL_SECS;
    return (time() - strtotime($last)) >= $gap;
}

// ─── Claude API Call ──────────────────────────────────────────────────────────
function call_claude(string $topic, array $books): string {
    $system = 'You are a senior healthcare cybersecurity analyst and technical writer. '
            . 'Your audience is health system CISOs, compliance officers, and clinical informatics leaders. '
            . 'Write with authority, cite real frameworks (NIST CSF, HIPAA Security Rule, HITRUST, FAIR, CIS Controls), '
            . 'and provide actionable, practitioner-level guidance. Tone: professional but accessible.';

    $prompt = build_prompt($topic, $books);

    $payload = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => CLAUDE_MAX_TOKENS,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);

    $max_attempts = 3;
    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $ch = curl_init(CLAUDE_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 120,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new RuntimeException("cURL error: {$err}");

        if ($code === 529 || $code === 429) {
            log_msg("Rate limited (HTTP {$code}), attempt {$attempt}/{$max_attempts}. Waiting...");
            sleep(30 * $attempt);
            continue;
        }

        if ($code !== 200) {
            throw new RuntimeException("Claude API returned HTTP {$code}: {$body}");
        }

        $data = json_decode($body, true);
        if (!isset($data['content'][0]['text'])) {
            throw new RuntimeException('Unexpected Claude response structure');
        }
        return $data['content'][0]['text'];
    }
    throw new RuntimeException("Claude API failed after {$max_attempts} attempts");
}

// ─── Prompt Builder ───────────────────────────────────────────────────────────
function build_prompt(string $topic, array $books): string {
    // Format the 3 pre-selected books for injection into the prompt
    $book_lines = '';
    foreach ($books as $i => $book) {
        $n = $i + 1;
        $book_lines .= "  Book {$n}: \"{$book['title']}\" by {$book['author']}\n";
    }

    return <<<PROMPT
Write a detailed, authoritative blog post for healthcare cybersecurity professionals on this topic:

TOPIC: {$topic}

Requirements:
- 700-900 words of body content
- Professional healthcare cybersecurity publication style (think: Health Affairs, HIMSS, CHIME)
- Include practical, actionable guidance that a real CISO or compliance officer can use
- Reference real frameworks, regulations, or standards where relevant (NIST CSF, HIPAA, HITRUST, FAIR, CIS, etc.)
- Include subheadings (use <h2> and <h3> tags)
- Body should be fully formatted as HTML paragraphs — no markdown, no plain text

The following 3 books have been pre-selected from our curated library for this post.
Write one focused sentence for each explaining why it is directly relevant to THIS post's specific topic.

{$book_lines}
Also provide 3-5 photo search keywords (English, comma-separated, no hashtags) for a
relevant professional stock photo. Think: what visual scene best represents this topic?
(e.g. "hospital server room, cybersecurity", "doctor laptop security", "data breach alert screen")

Return your response ONLY as the following XML structure — no text before or after:

<post>
<slug>url-friendly-slug-here</slug>
<title>Full Post Title Here</title>
<excerpt>A compelling 2-sentence summary for the homepage card.</excerpt>
<meta_description>SEO meta description under 160 characters.</meta_description>
<tags>tag1, tag2, tag3, tag4</tags>
<image_keywords>hospital,cybersecurity,data-security</image_keywords>
<body>
<h2>First Section Heading</h2>
<p>Body content with real HTML markup...</p>
</body>
<book1_reason>One sentence explaining why Book 1 is relevant to this specific post topic.</book1_reason>
<book2_reason>One sentence explaining why Book 2 is relevant to this specific post topic.</book2_reason>
<book3_reason>One sentence explaining why Book 3 is relevant to this specific post topic.</book3_reason>
</post>
PROMPT;
}

// ─── Response Parser ──────────────────────────────────────────────────────────
function parse_post_response(string $raw, array $selected_books): array {
    if (!preg_match('/<post>(.*?)<\/post>/s', $raw, $m)) {
        throw new RuntimeException('No <post> block found in Claude response');
    }
    $xml = $m[1];

    $fields = ['slug', 'title', 'excerpt', 'meta_description', 'tags', 'body'];
    $data   = [];
    foreach ($fields as $f) {
        if (!preg_match('/<' . $f . '>(.*?)<\/' . $f . '>/s', $xml, $fm)) {
            throw new RuntimeException("Missing field: <{$f}>");
        }
        $data[$f] = trim($fm[1]);
    }

    // Extract image keywords (fall back to tags)
    if (preg_match('/<image_keywords>(.*?)<\/image_keywords>/s', $xml, $ikm)) {
        $data['image_keywords'] = trim($ikm[1]);
    } else {
        $data['image_keywords'] = $data['tags'];
    }

    // Sanitize slug
    $data['slug'] = make_slug($data['slug']);

    // Books: use pre-selected title/author/search; Claude provides only the reason
    $data['books'] = [];
    for ($i = 1; $i <= 3; $i++) {
        $reason = "A highly recommended resource for this topic.";
        if (preg_match('/<book' . $i . '_reason>(.*?)<\/book' . $i . '_reason>/s', $xml, $bm)) {
            $reason = trim($bm[1]);
        }
        $book = $selected_books[$i - 1];
        $data['books'][] = [
            'position'     => $i,
            'title'        => $book['title'],
            'author'       => $book['author'],
            'reason'       => $reason,
            'search_query' => $book['search'],
        ];
    }

    return $data;
}

// ─── DB Insert ────────────────────────────────────────────────────────────────
function insert_post(PDO $db, array $data, string $topic, string $category_slug = ''): void {
    // Resolve category: use explicit slug first, then fall back to tag-based detection
    $category_id = null;
    if ($category_slug) {
        $stmt = $db->prepare('SELECT id FROM categories WHERE slug = ?');
        $stmt->execute([$category_slug]);
        $category_id = $stmt->fetchColumn() ?: null;
    }
    if (!$category_id) {
        $category_id = resolve_category($db, $data['tags']);
    }

    // Get category color for thumbnail gradient
    $color = '#1a73e8';
    if ($category_id) {
        $cs = $db->prepare('SELECT color FROM categories WHERE id = ?');
        $cs->execute([$category_id]);
        $color = $cs->fetchColumn() ?: $color;
    }

    $thumbnail_css = post_thumbnail_css($data['slug'], $color);

    // Fetch photo from Pexels (or Picsum fallback)
    $photo_url = fetch_unsplash_photo($data['image_keywords']);

    // Ensure unique slug
    $slug  = $data['slug'];
    $check = $db->prepare('SELECT id FROM posts WHERE slug = ?');
    $check->execute([$slug]);
    if ($check->fetch()) {
        $slug = $slug . '-' . date('Ymd');
    }

    $db->prepare(
        'INSERT INTO posts (slug, title, excerpt, meta_description, body, tags, category_id, thumbnail_css, photo_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $slug,
        $data['title'],
        $data['excerpt'],
        $data['meta_description'],
        $data['body'],
        $data['tags'],
        $category_id,
        $thumbnail_css,
        $photo_url,
    ]);

    $post_id = (int)$db->lastInsertId();

    $book_stmt = $db->prepare(
        'INSERT INTO affiliate_books (post_id, position, title, author, reason, search_query)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($data['books'] as $book) {
        $book_stmt->execute([
            $post_id,
            $book['position'],
            $book['title'],
            $book['author'],
            $book['reason'],
            $book['search_query'],
        ]);
    }
}

function resolve_category(PDO $db, string $tags): ?int {
    $map = [
        'hipaa'            => 'hipaa',
        'hitech'           => 'hipaa',
        'phi'              => 'hipaa',
        'breach notification' => 'hipaa',
        'nist'             => 'frameworks',
        'hitrust'          => 'frameworks',
        'iso'              => 'frameworks',
        'cis controls'     => 'frameworks',
        'fair model'       => 'frameworks',
        'ransomware'       => 'ransomware',
        'malware'          => 'ransomware',
        'extortion'        => 'ransomware',
        'incident'         => 'cyber-risk',
        'risk'             => 'cyber-risk',
        'vulnerability'    => 'cyber-risk',
        'phishing'         => 'cyber-risk',
        'zero trust'       => 'cyber-risk',
        'privacy'          => 'privacy',
        'gdpr'             => 'privacy',
        'data protection'  => 'privacy',
        'compliance'       => 'compliance',
        'regulation'       => 'compliance',
        'audit'            => 'compliance',
        'ai'               => 'ai-implementation',
        'machine learning' => 'ai-implementation',
        'algorithm'        => 'ai-implementation',
        'generative'       => 'ai-implementation',
        'llm'              => 'ai-implementation',
    ];
    $tags_lower = strtolower($tags);
    foreach ($map as $keyword => $cat_slug) {
        if (str_contains($tags_lower, $keyword)) {
            $stmt = $db->prepare('SELECT id FROM categories WHERE slug = ?');
            $stmt->execute([$cat_slug]);
            $id = $stmt->fetchColumn();
            if ($id) return (int)$id;
        }
    }
    return null;
}

function post_thumbnail_css(string $slug, string $color = '#1a73e8'): string {
    $hue  = abs(crc32($slug)) % 360;
    $hue2 = ($hue + 40) % 360;
    return "linear-gradient(135deg, {$color}, hsl({$hue2},55%,28%))";
}

// ─── Photo Fetcher — Pexels with Picsum fallback ──────────────────────────────
function fetch_unsplash_photo(string $keywords): string {
    if (PEXELS_API_KEY) {
        $photo = fetch_pexels_photo($keywords);
        if ($photo) return $photo;
    }
    $seed = substr(preg_replace('/[^a-z0-9]/', '', strtolower($keywords)), 0, 40);
    log_msg("Pexels unavailable — using Picsum fallback for seed: {$seed}");
    return "https://picsum.photos/seed/{$seed}/1200/630";
}

function fetch_pexels_photo(string $keywords): string {
    $clean = trim(preg_replace('/[^a-z0-9,\- ]/i', ' ', $keywords));
    $query = urlencode(preg_replace('/\s+/', ' ', str_replace(',', ' ', $clean)));

    $ch = curl_init("https://api.pexels.com/v1/search?query={$query}&per_page=1&orientation=landscape");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . PEXELS_API_KEY],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'HealthCyberInsights/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        log_msg("Pexels API returned HTTP {$code}");
        return '';
    }

    $data = json_decode($body, true);
    $url  = $data['photos'][0]['src']['large2x'] ?? '';

    if ($url) {
        log_msg("Pexels photo found: {$url}");
        return $url;
    }

    log_msg("No Pexels results for '{$query}', retrying with broader terms");
    return fetch_pexels_photo('healthcare technology security');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function make_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function log_generation(string $status, ?string $topic, string $message): void {
    get_db()->prepare(
        'INSERT INTO generation_log (status, topic, message) VALUES (?, ?, ?)'
    )->execute([$status, $topic, $message]);
}

function log_msg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_PATH, $line, FILE_APPEND | LOCK_EX);
    if (php_sapi_name() === 'cli') echo $line;
}
