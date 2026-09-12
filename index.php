<?php
require_once __DIR__ . '/includes/auth.php';

initSession();

// If admin is already authenticated, go directly to admin dashboard
if (checkAdminAuth()) {
    header('Location: admin/dashboard.php?token=' . urlencode(getActiveAdminToken()));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Interview System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. If localStorage has admin_token, prepare quick redirect for admin links
            try {
                const token = localStorage.getItem('admin_token');
                if (token) {
                    document.querySelectorAll('.admin-nav-link').forEach(link => {
                        link.href = 'admin/dashboard.php?token=' + encodeURIComponent(token);
                    });
                }
            } catch(e) {}

            // 2. Handle candidate interview link input & clipboard paste
            const linkForm = document.getElementById('candidateLinkForm');
            const linkInput = document.getElementById('candidateLinkInput');
            const pasteBtn = document.getElementById('pasteClipboardBtn');
            const errorMsg = document.getElementById('linkErrorMsg');

            // Quick paste button
            if (pasteBtn && linkInput) {
                pasteBtn.addEventListener('click', async () => {
                    try {
                        if (navigator.clipboard && navigator.clipboard.readText) {
                            const clipText = await navigator.clipboard.readText();
                            if (clipText) {
                                linkInput.value = clipText.trim();
                                if (errorMsg) errorMsg.style.display = 'none';
                                linkInput.focus();
                            }
                        } else {
                            linkInput.focus();
                            linkInput.select();
                        }
                    } catch (err) {
                        linkInput.focus();
                        linkInput.select();
                    }
                });
            }

            // Intercept form submit and parse URL or token seamlessly
            if (linkForm && linkInput) {
                linkForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    let rawVal = (linkInput.value || '').trim();
                    if (!rawVal) {
                        if (errorMsg) {
                            errorMsg.textContent = 'Please paste your interview link or token.';
                            errorMsg.style.display = 'block';
                        }
                        linkInput.focus();
                        return;
                    }

                    // Strip any accidental leading/trailing quotes or whitespace
                    rawVal = rawVal.replace(/^["']|["']$/g, '');

                    // Case A: Parameter token= in string
                    const tokenMatch = rawVal.match(/[?&]token=([^&#\s]+)/i);
                    if (tokenMatch && tokenMatch[1]) {
                        const extractedToken = decodeURIComponent(tokenMatch[1]);
                        window.location.href = 'candidate/interview.php?token=' + encodeURIComponent(extractedToken);
                        return;
                    }

                    // Case B: Full URL (same origin or external URL pointing to candidate interview)
                    if (/^https?:\/\//i.test(rawVal)) {
                        try {
                            const urlObj = new URL(rawVal);
                            const tParam = urlObj.searchParams.get('token');
                            if (tParam) {
                                window.location.href = 'candidate/interview.php?token=' + encodeURIComponent(tParam);
                                return;
                            }
                            if (urlObj.pathname.includes('interview.php')) {
                                window.location.href = urlObj.href;
                                return;
                            }
                        } catch (err) {
                            console.warn('URL parsing fallback:', err);
                        }
                    }

                    // Case C: Relative path
                    if (rawVal.includes('candidate/interview.php')) {
                        window.location.href = rawVal;
                        return;
                    }

                    // Case D: Pure token string
                    window.location.href = 'candidate/interview.php?token=' + encodeURIComponent(rawVal);
                });

                linkInput.addEventListener('input', () => {
                    if (errorMsg) errorMsg.style.display = 'none';
                });
            }
        });
    </script>
</head>
<body style="background-color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column;">

<header class="site-header">
    <div class="container header-inner">
        <a href="index.php" class="brand-link">
            <span>AI Interview System</span>
        </a>
        <a href="admin/login.php" class="btn btn-outline btn-sm admin-nav-link" id="headerAdminBtn">Admin Sign In</a>
    </div>
</header>

<main class="container" style="flex: 1; display: flex; align-items: center; justify-content: center;">
    <div style="max-width: 780px; width: 100%; text-align: center; margin: 36px 0;">
        <h1 style="font-size: 2.2rem; margin-bottom: 12px; color: var(--text-main);">AI-Powered Technical Screening</h1>
        <p style="font-size: 1.1rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 32px;">
            Structured continuous video interviews with voice question delivery and local AI evaluation.
        </p>

        <div class="home-grid">
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="color: var(--primary); margin-bottom: 8px;">For Administrators</h3>
                    <p style="font-size: 0.9rem; margin-bottom: 16px; line-height: 1.5;">
                        Create custom interviews, manage questions, register candidates, and view video recordings with AI scoring.
                    </p>
                    <div style="background: var(--bg-page); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 16px;">
                        <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-main); margin-bottom: 4px;">Recruiter & HR Management</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Manage candidate invitation links, track interview statuses, and inspect comprehensive AI evaluation reports.</div>
                    </div>
                </div>
                <div>
                    <a href="admin/login.php" class="btn btn-primary admin-nav-link" id="cardAdminBtn" style="width: 100%;">Admin Portal &rarr;</a>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="color: var(--text-main); margin-bottom: 8px;">For Candidates</h3>
                    <p style="font-size: 0.9rem; margin-bottom: 14px; line-height: 1.5;">
                        Paste the interview link or invitation token provided by your recruiter to begin your interview.
                    </p>

                    <!-- Paste / Enter Interview Link Form -->
                    <form id="candidateLinkForm" action="candidate/interview.php" method="GET" style="margin-bottom: 12px;">
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="candidateLinkInput" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-main); margin-bottom: 5px;">
                                Paste Interview Link or Token:
                            </label>
                            <div style="display: flex; gap: 6px;">
                                <input 
                                    type="text" 
                                    id="candidateLinkInput" 
                                    name="token" 
                                    class="form-control" 
                                    placeholder="Paste given link or token here..." 
                                    style="font-size: 0.85rem; padding: 8px 10px; flex: 1;"
                                    autocomplete="off"
                                    required
                                >
                                <button type="button" id="pasteClipboardBtn" class="btn btn-outline btn-sm" style="white-space: nowrap; padding: 0 10px; font-size: 0.8rem;" title="Paste from clipboard">
                                    Paste
                                </button>
                            </div>
                            <div id="linkErrorMsg" style="display: none; color: var(--danger); font-size: 0.78rem; margin-top: 5px;"></div>
                        </div>

                        <button type="submit" id="startInterviewBtn" class="btn btn-primary" style="width: 100%;">
                            Give Interview &rarr;
                        </button>
                    </form>
                </div>

                <div style="padding-top: 10px; border-top: 1px dashed var(--border-color); text-align: center;">
                    <span style="font-size: 0.78rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Don't have an invitation link?</span>
                    <a href="candidate/interview.php?token=demo-token-alex-123" class="btn btn-outline btn-sm" style="width: 100%; font-size: 0.82rem;">Try Demo Interview</a>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> AI Interview System &bull; Built with PHP, MySQL/SQLite, HTML, CSS, and Vanilla JavaScript</p>
    </div>
</footer>

</body>
</html>
