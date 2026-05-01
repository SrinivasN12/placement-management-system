<?php
// config/qa_setup.php — Creates Q&A tables if they don't exist
// Include this on any Q&A page (runs only once per connection; no-op if tables exist)

function qa_setup(mysqli $conn): void {
    // Questions posted by students
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS qa_question (
            question_id  INT          AUTO_INCREMENT PRIMARY KEY,
            student_id   INT          NOT NULL,
            title        VARCHAR(300) NOT NULL,
            body         TEXT         NOT NULL,
            tags         VARCHAR(200) DEFAULT '',
            is_resolved  TINYINT(1)   DEFAULT 0,
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Answers posted by alumni
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS qa_answer (
            answer_id   INT      AUTO_INCREMENT PRIMARY KEY,
            question_id INT      NOT NULL,
            alumni_id   INT      NOT NULL,
            body        TEXT     NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (question_id) REFERENCES qa_question(question_id) ON DELETE CASCADE,
            FOREIGN KEY (alumni_id)   REFERENCES alumni(alumni_id)         ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Helpful-votes on answers (students only, one per answer)
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS qa_vote (
            vote_id    INT AUTO_INCREMENT PRIMARY KEY,
            answer_id  INT NOT NULL,
            student_id INT NOT NULL,
            UNIQUE KEY uq_sv (student_id, answer_id),
            FOREIGN KEY (answer_id)  REFERENCES qa_answer(answer_id)   ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
?>
