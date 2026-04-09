<?php
/**
 * Post Generator — triggered by cron-job.org (HTTP) or CLI (--local flag)
 *
 * HTTP usage:  https://yourdomain.com/generate.php?token=YOUR_CRON_TOKEN
 * CLI usage:   php generate.php --local
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

// ─── Topic Pool ───────────────────────────────────────────────────────────────
$topics = [
    // HIPAA
    'HIPAA Security Rule Risk Analysis: A Practical Framework for Hospitals',
    'Workforce Training Under HIPAA: Building a Culture of Compliance',
    'Business Associate Agreements: Managing Third-Party Cyber Risk in Healthcare',
    'HIPAA Breach Notification Rule: Response Timelines and Reporting Obligations',
    'Minimum Necessary Standard: Limiting PHI Access in Clinical Workflows',
    // NIST
    'Applying NIST CSF 2.0 to Healthcare Organizations',
    'NIST SP 800-66: Implementing the HIPAA Security Rule',
    'Zero Trust Architecture for Hospitals Using NIST 800-207',
    'NIST AI Risk Management Framework in Clinical AI Deployments',
    'Using NIST Privacy Framework to Align with HIPAA and State Laws',
    // Ransomware & Cyber Risk
    'Ransomware Preparedness for Healthcare: Detection, Containment, Recovery',
    'Healthcare Sector Ransomware Trends: Lessons from Recent Attacks',
    'Medical Device Cybersecurity: Managing Risk in Connected Care Environments',
    'Third-Party Risk Management in Healthcare Supply Chains',
    'Incident Response Planning for Health Systems: A Step-by-Step Guide',
    'Phishing Defense in Healthcare: Protecting Clinicians from Social Engineering',
    'Insider Threat Programs for Hospitals and Health Networks',
    'Vulnerability Management Priorities for Healthcare IT Teams',
    'Cloud Security Posture in Healthcare: Shared Responsibility and Compliance',
    'OT/ICS Security for Hospital Building Management and Medical Equipment',
    // Privacy & Regulations
    'State Health Privacy Laws Beyond HIPAA: California CMIA and Beyond',
    'GDPR Impact on US Healthcare Organizations with EU Patients',
    'Patient Data Monetization: Legal Boundaries and Ethical Considerations',
    'De-identification vs. Anonymization of Health Data: Technical and Legal Standards',
    'Mental Health Data Privacy: 42 CFR Part 2 and New Federal Rules',
    'Reproductive Health Data Privacy After Dobbs: Legal Exposure for Providers',
    'FTC Health Breach Notification Rule: What Non-HIPAA Entities Must Know',
    'Data Retention and Destruction Policies for Healthcare Records',
    // AI Implementation
    'AI Governance Frameworks for Clinical Decision Support Systems',
    'FDA AI/ML Software as a Medical Device (SaMD) Regulatory Pathway',
    'Bias and Fairness Auditing in Healthcare AI Models',
    'Explainable AI in Radiology: Building Clinician Trust',
    'Generative AI in EHR Documentation: Security and Compliance Considerations',
    'AI-Powered Predictive Analytics for Hospital Sepsis Detection',
    'Procurement Checklist for AI Vendors in Healthcare Settings',
    'AI Model Drift in Clinical Environments: Monitoring and Retraining',
    'ChatGPT and LLMs in Clinical Practice: Risk Management and Guardrails',
    'Patient Consent for AI Use in Diagnosis and Treatment Planning',
    // Compliance & Frameworks
    'HITRUST CSF r2: Mapping to HIPAA, NIST, and SOC 2 for Health Orgs',
    'SOC 2 Type II for Healthcare SaaS: What Covered Entities Must Verify',
    'CIS Controls v8 Prioritized Implementation for Health Systems',
    'ISO 27001 Certification for Healthcare Organizations: Roadmap',
    'CMS Interoperability and Patient Access Final Rule: Security Implications',
    'ONC TEFCA and Health Information Networks: Privacy and Security Standards',
    'Cybersecurity Maturity Model for Rural and Critical Access Hospitals',
    'Board-Level Cybersecurity Governance in Health Systems',
    'Building a Healthcare CISO Program from Scratch',
    'Cyber Insurance for Healthcare: Coverage Gaps and Requirements',
    // Clinical AI & Patient Safety
    'AI in Clinical Trials: Data Integrity, Privacy, and Regulatory Compliance',
    'Algorithmic Accountability in Population Health Management',
    'Telehealth Security: Protecting Patient Data in Virtual Care',
    'Securing Health IoT: Wearables, Remote Monitoring, and Connected Devices',
    'Digital Pathology and AI: Data Security in Precision Medicine',
    'Interoperability Security: FHIR APIs and PHI Exposure Risks',
    'Patient Portal Security: Multifactor Authentication and Access Controls',
    'Securing Genomic Data: Privacy Risks in Precision Medicine',
    'AI Triage Tools in Emergency Departments: Risk and Liability',
    'Medication Safety and AI: Preventing Algorithmic Errors in Prescribing',
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
    $db          = get_db();
    $topic_index = (int)get_setting('last_topic_index', '0');
    $topic       = $topics[$topic_index % count($topics)];

    // Skip if slug already exists
    $slug_candidate = make_slug($topic);
    $exists = $db->prepare('SELECT id FROM posts WHERE slug LIKE ?');
    $exists->execute([$slug_candidate . '%']);
    if ($exists->fetch()) {
        $topic_index++;
        $topic = $topics[$topic_index % count($topics)];
    }

    log_msg("Generating post for topic: {$topic}");

    $response  = call_claude($topic);
    $post_data = parse_post_response($response);

    insert_post($db, $post_data, $topic);
    set_setting('last_topic_index', (string)(($topic_index + 1) % count($topics)));
    log_generation('success', $topic, 'Post created: ' . $post_data['slug']);
    log_msg("SUCCESS: Post '{$post_data['title']}' created (slug: {$post_data['slug']})");

} catch (Throwable $e) {
    $msg = 'ERROR: ' . $e->getMessage();
    log_generation('error', $topic ?? null, $msg);
    log_msg($msg);
    if ($is_cli) echo $msg . PHP_EOL;
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
function call_claude(string $topic): string {
    $system = 'You are a senior healthcare cybersecurity analyst and technical writer. '
            . 'Your audience is health system CISOs, compliance officers, and clinical informatics leaders. '
            . 'Write with authority, cite real frameworks (NIST CSF, HIPAA Security Rule, HITRUST), '
            . 'and provide actionable guidance. Tone: professional but accessible.';

    $prompt = build_prompt($topic);

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
function build_prompt(string $topic): string {
    return <<<PROMPT
Write a detailed, authoritative blog post for healthcare cybersecurity professionals on this topic:

TOPIC: {$topic}

Requirements:
- 700-900 words of body content
- Professional healthcare cybersecurity publication style
- Include practical, actionable guidance
- Reference real frameworks, regulations, or standards where relevant
- Include subheadings (use <h2> and <h3> tags)
- Body should be formatted as HTML paragraphs

Also recommend 3 real books available on Amazon that are directly relevant to this topic.
Books should be real, published after 2015 if possible, and widely available.

Also provide 3-5 photo search keywords (in English, comma-separated, no hashtags) that would
find a relevant, professional stock photo for this post on Unsplash. Think: what visual scene
best represents this topic? (e.g. "hospital server room, cybersecurity", "doctor laptop privacy",
"data breach alert screen").

Return your response ONLY as the following XML structure with no text before or after it:

<post>
<slug>url-friendly-slug-here</slug>
<title>Full Post Title Here</title>
<excerpt>A compelling 2-sentence summary of the post for the homepage card.</excerpt>
<meta_description>SEO meta description under 160 characters.</meta_description>
<tags>tag1, tag2, tag3, tag4</tags>
<image_keywords>hospital,cybersecurity,data-security</image_keywords>
<body>
<h2>Introduction heading here</h2>
<p>Body paragraphs with real HTML markup here...</p>
</body>
<book1_title>Book Title Here</book1_title>
<book1_author>Author Name</book1_author>
<book1_reason>One sentence explaining why this book is relevant to the post topic.</book1_reason>
<book1_search>Search query to find this book on Amazon</book1_search>
<book2_title>Book Title Here</book2_title>
<book2_author>Author Name</book2_author>
<book2_reason>One sentence explaining why this book is relevant to the post topic.</book2_reason>
<book2_search>Search query to find this book on Amazon</book2_search>
<book3_title>Book Title Here</book3_title>
<book3_author>Author Name</book3_author>
<book3_reason>One sentence explaining why this book is relevant to the post topic.</book3_reason>
<book3_search>Search query to find this book on Amazon</book3_search>
</post>
PROMPT;
}

// ─── Response Parser ──────────────────────────────────────────────────────────
function parse_post_response(string $raw): array {
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

    // Extract image keywords (optional — fall back to tags if missing)
    if (preg_match('/<image_keywords>(.*?)<\/image_keywords>/s', $xml, $ikm)) {
        $data['image_keywords'] = trim($ikm[1]);
    } else {
        $data['image_keywords'] = $data['tags'];
    }

    // Sanitize slug
    $data['slug'] = make_slug($data['slug']);

    // Extract 3 books
    $data['books'] = [];
    for ($i = 1; $i <= 3; $i++) {
        $book = ['position' => $i];
        foreach (['title', 'author', 'reason', 'search'] as $bf) {
            $tag = "book{$i}_{$bf}";
            if (!preg_match('/<' . $tag . '>(.*?)<\/' . $tag . '>/s', $xml, $bm)) {
                throw new RuntimeException("Missing: <{$tag}>");
            }
            $key = ($bf === 'search') ? 'search_query' : $bf;
            $book[$key] = trim($bm[1]);
        }
        $data['books'][] = $book;
    }
    return $data;
}

// ─── DB Insert ────────────────────────────────────────────────────────────────
function insert_post(PDO $db, array $data, string $topic): void {
    // Resolve category from tags
    $category_id = resolve_category($db, $data['tags']);

    // Get category color for thumbnail
    $color = '#1a73e8';
    if ($category_id) {
        $cs = $db->prepare('SELECT color FROM categories WHERE id = ?');
        $cs->execute([$category_id]);
        $color = $cs->fetchColumn() ?: $color;
    }

    $thumbnail_css = post_thumbnail_css($data['slug'], $color);

    // Fetch a real photo from Unsplash based on Claude's image keywords
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
        'hipaa'          => 'hipaa',
        'hitech'         => 'hipaa',
        'phi'            => 'hipaa',
        'nist'           => 'frameworks',
        'hitrust'        => 'frameworks',
        'iso'            => 'frameworks',
        'cis'            => 'frameworks',
        'ransomware'     => 'ransomware',
        'malware'        => 'ransomware',
        'incident'       => 'cyber-risk',
        'risk'           => 'cyber-risk',
        'vulnerability'  => 'cyber-risk',
        'phishing'       => 'cyber-risk',
        'privacy'        => 'privacy',
        'gdpr'           => 'privacy',
        'data breach'    => 'privacy',
        'compliance'     => 'compliance',
        'regulation'     => 'compliance',
        'ai'             => 'ai-implementation',
        'machine learning' => 'ai-implementation',
        'algorithm'      => 'ai-implementation',
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

// ─── Pexels Photo Fetcher — content-relevant, searches by post keywords ───────
function fetch_unsplash_photo(string $keywords): string {
    // Try Pexels API first (content-relevant photos)
    if (PEXELS_API_KEY) {
        $photo = fetch_pexels_photo($keywords);
        if ($photo) return $photo;
    }

    // Fallback: Picsum (deterministic per slug, always works, no API key)
    $seed = substr(preg_replace('/[^a-z0-9]/', '', strtolower($keywords)), 0, 40);
    log_msg("Pexels unavailable — using Picsum fallback for seed: {$seed}");
    return "https://picsum.photos/seed/{$seed}/1200/630";
}

function fetch_pexels_photo(string $keywords): string {
    // Build a clean search query — Pexels works best with 2-4 concise terms
    $clean = trim(preg_replace('/[^a-z0-9,\- ]/i', ' ', $keywords));
    // Replace commas with spaces, collapse whitespace
    $query = preg_replace('/\s+/', ' ', str_replace(',', ' ', $clean));
    $query = urlencode(trim($query));

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

    // If no results for specific keywords, retry with broader healthcare+tech terms
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
