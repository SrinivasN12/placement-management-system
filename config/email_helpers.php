<?php
// =============================================================
//  config/email_helpers.php — Email notification helpers
//
//  Two helpers are defined here:
//    1. send_placement_drive_emails($drive_id, $conn)
//       — Call immediately after a new PLACEMENT_DRIVE row is inserted.
//       — Queries eligible students and emails + notifies each one.
//
//    2. send_alumni_recommendation_emails($recommendation_id, $conn)
//       — Call immediately after a new ALUMNI_RECOMMENDATION row is inserted.
//       — Emails all active students about the alumni job recommendation.
//
//  Both helpers use mysqli (matching the rest of this project).
//  DB operations succeed independently of email delivery.
// =============================================================

require_once __DIR__ . '/mailer.php';

// ── Helper 1: Placement Drive Emails ─────────────────────────

/**
 * Email all eligible students when a new placement drive is posted.
 *
 * Eligibility: cgpa >= min_cgpa AND backlogs <= max_backlogs
 *              AND tenth_percentage >= min_tenth_pct
 *              AND twelfth_percentage >= min_twelfth_pct
 *              AND degree_cgpa >= min_degree_cgpa
 *              AND user.status = 'Active'
 *
 * @param int    $drive_id  The newly inserted placement_drive.drive_id.
 * @param mysqli $conn      Active mysqli connection.
 */
function send_placement_drive_emails(int $drive_id, $conn): void
{
    // ── 1. Fetch drive details ────────────────────────────────
    $stmt = mysqli_prepare($conn,
        "SELECT job_title, company_name, description,
                min_cgpa, max_backlogs,
                min_tenth_pct, min_twelfth_pct, min_degree_cgpa,
                drive_date, last_date
         FROM   placement_drive
         WHERE  drive_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $drive_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $drive  = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$drive) {
        return; // Drive not found — nothing to do
    }

    // ── 2. Find eligible students ─────────────────────────────
    $stmt2 = mysqli_prepare($conn,
        "SELECT s.student_id, s.name, u.email
         FROM   student s
         JOIN   user   u ON u.user_id = s.user_id
         WHERE  u.status           = 'Active'
           AND  s.cgpa             >= ?
           AND  s.backlogs         <= ?
           AND  s.tenth_percentage >= ?
           AND  s.twelfth_percentage >= ?
           AND  s.degree_cgpa      >= ?");
    mysqli_stmt_bind_param($stmt2, "diddd",
        $drive['min_cgpa'],
        $drive['max_backlogs'],
        $drive['min_tenth_pct'],
        $drive['min_twelfth_pct'],
        $drive['min_degree_cgpa']
    );
    mysqli_stmt_execute($stmt2);
    $students = mysqli_stmt_get_result($stmt2);

    $today       = date('Y-m-d');
    $driveDateFmt = date('d M Y', strtotime($drive['drive_date']));
    $lastDateFmt  = date('d M Y', strtotime($drive['last_date']));

    while ($student = mysqli_fetch_assoc($students)) {
        // ── 3a. Build HTML email ──────────────────────────────
        $emailBody = buildPlacementDriveEmail(
            $student['name'],
            $drive['job_title'],
            $drive['company_name'],
            $drive['description'],
            $driveDateFmt,
            $lastDateFmt,
            (float)$drive['min_cgpa'],
            (int)$drive['max_backlogs'],
            (float)$drive['min_tenth_pct'],
            (float)$drive['min_twelfth_pct'],
            (float)$drive['min_degree_cgpa']
        );

        // ── 3b. Send email (independently — DB must not depend on this) ──
        try {
            sendEmail(
                $student['email'],
                "🎯 New Placement Drive: {$drive['job_title']} at {$drive['company_name']}",
                $emailBody
            );
        } catch (\Throwable $e) {
            // Silently log; do not interrupt the request
            error_log('[send_placement_drive_emails] ' . $e->getMessage());
        }

        // ── 3c. Insert notification record ────────────────────
        $notifMsg = "New placement drive: {$drive['job_title']} at {$drive['company_name']}. "
                  . "Drive date: {$driveDateFmt}. Last date to apply: {$lastDateFmt}. "
                  . "Check eligibility and apply from your dashboard.";
        $stmt3 = mysqli_prepare($conn,
            "INSERT INTO notification (student_id, message, is_read, date)
             VALUES (?, ?, 0, ?)");
        mysqli_stmt_bind_param($stmt3, "iss",
            $student['student_id'], $notifMsg, $today);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);
    }

    mysqli_stmt_close($stmt2);
}

// ── Helper 2: Alumni Recommendation Emails ───────────────────

/**
 * Email all active students when an alumni posts a job recommendation.
 *
 * @param int    $recommendation_id  Newly inserted alumni_recommendation.recommendation_id.
 * @param mysqli $conn               Active mysqli connection.
 */
function send_alumni_recommendation_emails(int $recommendation_id, $conn): void
{
    // ── 1. Fetch recommendation details ──────────────────────
    $stmt = mysqli_prepare($conn,
        "SELECT ar.job_title, ar.company_name, ar.job_link, ar.description,
                al.name AS alumni_name, al.company_name AS alumni_company
         FROM   alumni_recommendation ar
         JOIN   alumni al ON al.alumni_id = ar.alumni_id
         WHERE  ar.recommendation_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $recommendation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rec    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$rec) {
        return;
    }

    // ── 2. Get all active students ────────────────────────────
    $stmt2 = mysqli_prepare($conn,
        "SELECT s.student_id, s.name, u.email
         FROM   student s
         JOIN   user   u ON u.user_id = s.user_id
         WHERE  u.status = 'Active'");
    mysqli_stmt_execute($stmt2);
    $students = mysqli_stmt_get_result($stmt2);

    $today = date('Y-m-d');

    while ($student = mysqli_fetch_assoc($students)) {
        // ── 3a. Build HTML email ──────────────────────────────
        $emailBody = buildAlumniRecommendationEmail(
            $student['name'],
            $rec['alumni_name'],
            $rec['alumni_company'],
            $rec['job_title'],
            $rec['company_name'],
            $rec['description'] ?? '',
            $rec['job_link']    ?? ''
        );

        // ── 3b. Send email ────────────────────────────────────
        try {
            sendEmail(
                $student['email'],
                "💼 Alumni Job Recommendation: {$rec['job_title']} at {$rec['company_name']}",
                $emailBody
            );
        } catch (\Throwable $e) {
            error_log('[send_alumni_recommendation_emails] ' . $e->getMessage());
        }

        // ── 3c. Insert notification record ────────────────────
        $notifMsg = "Alumni {$rec['alumni_name']} ({$rec['alumni_company']}) recommended a job: "
                  . "{$rec['job_title']} at {$rec['company_name']}. "
                  . "Contact the placement cell for more information.";
        $stmt3 = mysqli_prepare($conn,
            "INSERT INTO notification (student_id, message, is_read, date)
             VALUES (?, ?, 0, ?)");
        mysqli_stmt_bind_param($stmt3, "iss",
            $student['student_id'], $notifMsg, $today);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);
    }

    mysqli_stmt_close($stmt2);
}

// ─────────────────────────────────────────────────────────────
//  Email Template Builders
// ─────────────────────────────────────────────────────────────

/**
 * Build the HTML body for a placement drive notification email.
 */
function buildPlacementDriveEmail(
    string $studentName,
    string $jobTitle,
    string $company,
    string $description,
    string $driveDate,
    string $lastDate,
    float  $minCgpa,
    int    $maxBacklogs,
    float  $minTenth,
    float  $minTwelfth,
    float  $minDegree
): string {
    $desc        = nl2br(htmlspecialchars($description));
    $studentName = htmlspecialchars($studentName);
    $jobTitle    = htmlspecialchars($jobTitle);
    $company     = htmlspecialchars($company);
    $dashboardUrl = 'http://localhost/new_pms_ag_new/student/drives.php';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:32px 0;">
    <tr>
      <td align="center">
        <!-- Container -->
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.09);">

          <!-- Header -->
          <tr>
            <td style="background-color:#1a3c5e;padding:32px 40px;text-align:center;">
              <p style="margin:0;font-size:13px;color:#a8c4e0;letter-spacing:1px;text-transform:uppercase;">JSS SMC MCA Institute, Dharwad</p>
              <h1 style="margin:8px 0 0;font-size:22px;color:#ffffff;font-weight:700;">Placement Cell</h1>
              <p style="margin:6px 0 0;font-size:13px;color:#90bbd8;">New Placement Drive Alert 🎯</p>
            </td>
          </tr>

          <!-- Greeting -->
          <tr>
            <td style="padding:32px 40px 0;">
              <p style="margin:0;font-size:15px;color:#333333;">Dear <strong>{$studentName}</strong>,</p>
              <p style="margin:12px 0 0;font-size:14px;color:#555555;line-height:1.7;">
                Great news! A new placement drive has been posted and you are eligible to apply.
                Review the details below and submit your application before the deadline.
              </p>
            </td>
          </tr>

          <!-- Drive Details Card -->
          <tr>
            <td style="padding:24px 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f0f7ff;border-radius:10px;border:1px solid #d0e6f7;padding:24px;">
                <tr>
                  <td>
                    <h2 style="margin:0 0 4px;font-size:20px;color:#1a3c5e;">{$jobTitle}</h2>
                    <p style="margin:0 0 20px;font-size:14px;color:#2d6a9f;font-weight:600;">🏢 {$company}</p>

                    <table role="presentation" width="100%" cellpadding="6" cellspacing="0">
                      <tr>
                        <td width="50%" style="font-size:13px;color:#555;font-weight:600;">📅 Drive Date</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">{$driveDate}</td>
                      </tr>
                      <tr style="background:#e8f3fc;">
                        <td style="font-size:13px;color:#555;font-weight:600;">⏰ Last Date to Apply</td>
                        <td style="font-size:13px;color:#c0392b;font-weight:700;">{$lastDate}</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#555;font-weight:600;">🎓 Min MBA CGPA</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">{$minCgpa}</td>
                      </tr>
                      <tr style="background:#e8f3fc;">
                        <td style="font-size:13px;color:#555;font-weight:600;">📚 Max Active Backlogs</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">{$maxBacklogs}</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#555;font-weight:600;">📊 Min 10th %</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">{$minTenth}%</td>
                      </tr>
                      <tr style="background:#e8f3fc;">
                        <td style="font-size:13px;color:#555;font-weight:600;">📊 Min 12th %</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">{$minTwelfth}%</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#555;font-weight:600;">🏫 Min Degree CGPA</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">{$minDegree}</td>
                      </tr>
                      <tr style="background:#e8f3fc;">
                        <td style="font-size:13px;color:#555;font-weight:600;">🏷️ Department</td>
                        <td style="font-size:13px;color:#1a3c5e;font-weight:700;">MBA (All Students)</td>
                      </tr>
                    </table>

                    <!-- Description -->
                    <p style="margin:18px 0 6px;font-size:13px;color:#555;font-weight:600;">📋 Job Description</p>
                    <p style="margin:0;font-size:13px;color:#444;line-height:1.7;">{$desc}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- CTA Button -->
          <tr>
            <td style="padding:0 40px 32px;text-align:center;">
              <a href="{$dashboardUrl}"
                 style="display:inline-block;background-color:#1a3c5e;color:#ffffff;text-decoration:none;
                        padding:14px 36px;border-radius:8px;font-size:15px;font-weight:700;
                        letter-spacing:0.3px;margin-top:8px;">
                Apply Now on Student Dashboard →
              </a>
              <p style="margin:14px 0 0;font-size:12px;color:#999;">
                Log in to your student portal to apply before <strong style="color:#c0392b;">{$lastDate}</strong>.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f0f0f0;padding:22px 40px;text-align:center;border-top:1px solid #e0e0e0;">
              <p style="margin:0;font-size:12px;color:#888888;font-weight:600;">
                JSS SMC MCA Institute, Dharwad
              </p>
              <p style="margin:4px 0 0;font-size:11px;color:#aaaaaa;">
                This is an automated notification from the Placement Management System.<br>
                Please do not reply to this email.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Build the HTML body for an alumni job recommendation email.
 */
function buildAlumniRecommendationEmail(
    string $studentName,
    string $alumniName,
    string $alumniCompany,
    string $jobTitle,
    string $jobCompany,
    string $description,
    string $jobLink
): string {
    $studentName  = htmlspecialchars($studentName);
    $alumniName   = htmlspecialchars($alumniName);
    $alumniCompany= htmlspecialchars($alumniCompany);
    $jobTitle     = htmlspecialchars($jobTitle);
    $jobCompany   = htmlspecialchars($jobCompany);
    $desc         = nl2br(htmlspecialchars($description));
    $ctaButton    = $jobLink
        ? "<a href=\"" . htmlspecialchars($jobLink) . "\"
              style=\"display:inline-block;background-color:#1a3c5e;color:#ffffff;text-decoration:none;
                     padding:14px 36px;border-radius:8px;font-size:15px;font-weight:700;\">
             Apply for This Role →
           </a>"
        : "<p style=\"font-size:13px;color:#555;margin:0;\">
             Contact the Placement Cell for application details.
           </p>";

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:32px 0;">
    <tr>
      <td align="center">
        <!-- Container -->
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.09);">

          <!-- Header -->
          <tr>
            <td style="background-color:#1a3c5e;padding:32px 40px;text-align:center;">
              <p style="margin:0;font-size:13px;color:#a8c4e0;letter-spacing:1px;text-transform:uppercase;">JSS SMC MCA Institute, Dharwad</p>
              <h1 style="margin:8px 0 0;font-size:22px;color:#ffffff;font-weight:700;">Placement Cell</h1>
              <p style="margin:6px 0 0;font-size:13px;color:#90bbd8;">Alumni Job Recommendation 💼</p>
            </td>
          </tr>

          <!-- Alumni Badge -->
          <tr>
            <td style="padding:28px 40px 0;">
              <table role="presentation" cellpadding="0" cellspacing="0"
                     style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:16px 20px;width:100%;">
                <tr>
                  <td style="font-size:22px;width:48px;">🌟</td>
                  <td>
                    <p style="margin:0;font-size:14px;color:#333;font-weight:700;">{$alumniName}</p>
                    <p style="margin:2px 0 0;font-size:12px;color:#888;">Currently at <strong>{$alumniCompany}</strong> · JSS SMC MCA Alumni</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Greeting -->
          <tr>
            <td style="padding:24px 40px 0;">
              <p style="margin:0;font-size:15px;color:#333333;">Dear <strong>{$studentName}</strong>,</p>
              <p style="margin:12px 0 0;font-size:14px;color:#555555;line-height:1.7;">
                Our alumnus <strong>{$alumniName}</strong>, currently working at <strong>{$alumniCompany}</strong>,
                has recommended a job opportunity exclusively for students of JSS SMC MCA Institute.
                We encourage you to explore this opportunity and apply if it matches your profile.
              </p>
            </td>
          </tr>

          <!-- Job Details Card -->
          <tr>
            <td style="padding:24px 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f0f7ff;border-radius:10px;border:1px solid #d0e6f7;padding:24px;">
                <tr>
                  <td>
                    <h2 style="margin:0 0 4px;font-size:20px;color:#1a3c5e;">{$jobTitle}</h2>
                    <p style="margin:0 0 18px;font-size:14px;color:#2d6a9f;font-weight:600;">🏢 {$jobCompany}</p>

                    <p style="margin:0 0 6px;font-size:13px;color:#555;font-weight:600;">📋 Job Details / Description</p>
                    <p style="margin:0;font-size:13px;color:#444;line-height:1.7;">{$desc}</p>

                    <hr style="border:none;border-top:1px solid #d0e6f7;margin:18px 0;">
                    <p style="margin:0;font-size:13px;color:#777;font-style:italic;">
                      💡 <strong>Tip:</strong> Reach out to the Placement Cell or contact alumni directly
                      for referral support and interview preparation advice.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- CTA Button -->
          <tr>
            <td style="padding:0 40px 32px;text-align:center;">
              {$ctaButton}
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f0f0f0;padding:22px 40px;text-align:center;border-top:1px solid #e0e0e0;">
              <p style="margin:0;font-size:12px;color:#888888;font-weight:600;">
                JSS SMC MCA Institute, Dharwad
              </p>
              <p style="margin:4px 0 0;font-size:11px;color:#aaaaaa;">
                This is an automated notification from the Placement Management System.<br>
                Please do not reply to this email.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
