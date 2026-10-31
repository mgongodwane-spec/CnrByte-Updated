<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

$errors = [];

if (isset($_POST['create_banking_application'])) {
    $client_id = (int)($_SESSION['user_info']['id'] ?? 0);
    $application_type = trim((string)($_POST['application_type'] ?? ''));
    $account_subtype = trim((string)($_POST['account_subtype'] ?? ''));
    $savings_plan = trim((string)($_POST['savings_plan'] ?? ''));
    $identity_no = trim((string)($_POST['identity_no'] ?? ''));
    $reg_no = trim((string)($_POST['reg_no'] ?? ''));

    if ($client_id <= 0) {
        $errors['login'] = 'Please log in first.';
    }
    if (!in_array($application_type, ['personal', 'business', 'trust'], true)) {
        $errors['application_type'] = 'Choose a valid application type.';
    }

    $applicant_name = '';
    if ($application_type === 'personal') {
        $first_names = trim((string)($_POST['first_names'] ?? ''));
        $surname = trim((string)($_POST['surname'] ?? ''));
        if ($first_names === '' || $surname === '' || $identity_no === '') {
            $errors['personal_required'] = 'First names, surname, and identity number are required.';
        }
        $applicant_name = trim($first_names . ' ' . $surname);
    } elseif ($application_type === 'business') {
        $business_name = trim((string)($_POST['business_name'] ?? ''));
        if ($business_name === '' || $reg_no === '') {
            $errors['business_required'] = 'Business name and registration number are required.';
        }
        $applicant_name = $business_name;
    }

    // Trust application handling with members and beneficiaries
    if ($application_type === 'trust') {
        $trust_name = trim((string)($_POST['trust_name'] ?? ''));
        $trustee_name = trim((string)($_POST['trustee_name'] ?? ''));
        $trust_type = trim((string)($_POST['trust_type'] ?? ''));

        // Get members and beneficiaries from form arrays
        $member_names = is_array($_POST['member_name'] ?? null) ? array_values($_POST['member_name']) : [];
        $member_surnames = is_array($_POST['member_surname'] ?? null) ? array_values($_POST['member_surname']) : [];
        $member_id_nos = is_array($_POST['member_id_no'] ?? null) ? array_values($_POST['member_id_no']) : [];
        $member_deposits = is_array($_POST['member_deposit'] ?? null) ? array_values($_POST['member_deposit']) : [];
        $beneficiary_names = is_array($_POST['beneficiary_name'] ?? null) ? array_values($_POST['beneficiary_name']) : [];
        $beneficiary_relationships = is_array($_POST['beneficiary_relationship'] ?? null) ? array_values($_POST['beneficiary_relationship']) : [];
        $beneficiary_allocations = is_array($_POST['beneficiary_allocation'] ?? null) ? array_values($_POST['beneficiary_allocation']) : [];
        $beneficiary_member_indexes = is_array($_POST['beneficiary_member_index'] ?? null) ? array_values($_POST['beneficiary_member_index']) : [];

        if ($trust_name === '' || $trustee_name === '' || $trust_type === '') {
            $errors['trust_required'] = 'Trust name, trustee name and trust type are required.';
        }
        if (mb_strlen($trust_name) > 180 || mb_strlen($trustee_name) > 180 || !in_array($trust_type, ['family', 'charitable', 'investment', 'pension', 'other'], true)) {
            $errors['trust_values'] = 'Enter valid trust and trustee names and choose a supported trust type.';
        }

        $nonEmptyMembers = array_filter($member_names, static fn($value) => trim((string)$value) !== '');
        if (!$nonEmptyMembers) $errors['members'] = 'At least one member is required.';
        if (count($member_names) > 100 || count($beneficiary_names) > 300) $errors['trust_limits'] = 'A trust application may contain up to 100 members and 300 beneficiaries.';
        if (count($beneficiary_names) !== count($beneficiary_relationships) || count($beneficiary_names) !== count($beneficiary_allocations) || ($beneficiary_member_indexes && count($beneficiary_names) !== count($beneficiary_member_indexes))) {
            $errors['beneficiaries'] = 'Beneficiary details are incomplete. Please review the form and try again.';
        }
        foreach ($member_names as $index => $memberName) {
            $memberName = trim((string)$memberName);
            $surname = trim((string)($member_surnames[$index] ?? ''));
            $idNo = trim((string)($member_id_nos[$index] ?? ''));
            $depositRaw = trim((string)($member_deposits[$index] ?? '0'));
            if ($memberName !== '' && (mb_strlen($memberName) > 180 || mb_strlen($surname) > 180 || mb_strlen($idNo) > 100 || !is_numeric($depositRaw) || !is_finite((float)$depositRaw) || (float)$depositRaw < 0)) {
                $errors['member_' . $index] = 'Member names, identity details, and deposit amounts must be valid.';
                break;
            }
        }
        foreach ($beneficiary_names as $index => $beneficiaryName) {
            $beneficiaryName = trim((string)$beneficiaryName);
            if ($beneficiaryName === '') continue;
            $relationship = trim((string)($beneficiary_relationships[$index] ?? ''));
            $allocationRaw = trim((string)($beneficiary_allocations[$index] ?? '0'));
            $memberIndex = filter_var($beneficiary_member_indexes[$index] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (mb_strlen($beneficiaryName) > 180 || mb_strlen($relationship) > 120 || !is_numeric($allocationRaw) || !is_finite((float)$allocationRaw) || (float)$allocationRaw < 0 || (float)$allocationRaw > 100 || $memberIndex === false || $memberIndex >= count($member_names)) {
                $errors['beneficiary_' . $index] = 'Beneficiary details and allocation percentages must be valid.';
                break;
            }
        }
        $allocationTotals = [];
        foreach ($beneficiary_names as $index => $beneficiaryName) {
            if (trim((string)$beneficiaryName) === '') continue;
            $memberIndex = (int)($beneficiary_member_indexes[$index] ?? 0);
            $allocationTotals[$memberIndex] = ($allocationTotals[$memberIndex] ?? 0.0) + (float)($beneficiary_allocations[$index] ?? 0);
        }
        foreach ($allocationTotals as $allocationTotal) {
            if ($allocationTotal > 100.0001) {
                $errors['beneficiary_allocation_total'] = 'Beneficiary allocation totals for each member must not exceed 100%.';
                break;
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic();
    }

    $doc_path = null;
    $uploadedDocumentPath = null;
    if (isset($_FILES['doc_upload']) && (int)($_FILES['doc_upload']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $upload = $_FILES['doc_upload'];
        $ext = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
        $mimeMap = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']];
        if (!is_uploaded_file((string)$upload['tmp_name']) || (int)$upload['size'] <= 0 || (int)$upload['size'] > 10485760 || !isset($mimeMap[$ext])) {
            $_SESSION['errors_create'] = ['doc_upload' => 'Upload a valid PDF, PNG or JPEG file up to 10 MB.'];
            redirection_logic();
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!in_array((string)$finfo->file((string)$upload['tmp_name']), $mimeMap[$ext], true)) {
            $_SESSION['errors_create'] = ['doc_upload' => 'The document content does not match its file extension.'];
            redirection_logic();
        }
        $upload_dir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/cnrbyte/uploads/banking_docs';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0750, true) && !is_dir($upload_dir)) {
            $_SESSION['errors_create'] = ['doc_upload' => 'Document storage is unavailable.'];
            redirection_logic();
        }
        $filename = 'banking_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadedDocumentPath = $upload_dir . '/' . $filename;
        if (!move_uploaded_file((string)$upload['tmp_name'], $uploadedDocumentPath)) {
            $_SESSION['errors_create'] = ['doc_upload' => 'Document could not be saved.'];
            redirection_logic();
        }
        $doc_path = $filename;
    } elseif (isset($_FILES['doc_upload']) && (int)($_FILES['doc_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $_SESSION['errors_create'] = ['doc_upload' => 'The document upload failed.'];
        redirection_logic();
    }

    // Handle trust applications separately
    if ($application_type === 'trust') {
        try {
            $conn->begin_transaction();

            // Count members and beneficiaries
            $member_count = count(array_filter($member_names, static fn($value) => trim((string)$value) !== ''));
            $beneficiary_count = count(array_filter($beneficiary_names, static fn($value) => trim((string)$value) !== ''));

            // Insert trust application
            $stmt = $conn->prepare("
                INSERT INTO trust_applications
                (client_id, trust_name, trustee_name, trust_type, member_count, beneficiary_count, doc_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param(
                "isssiis",
                $client_id,
                $trust_name,
                $trustee_name,
                $trust_type,
                $member_count,
                $beneficiary_count,
                $doc_path
            );
            $stmt->execute();
            $trust_app_id = $conn->insert_id;
            $stmt->close();

            // Insert members and keep the actual database IDs for beneficiary ownership.
            $memberIdByIndex = [];
            $member_stmt = $conn->prepare("
                INSERT INTO trust_members
                (trust_application_id, member_name, member_surname, member_id_no, deposit_amount)
                VALUES (?, ?, ?, ?, ?)
            ");

            $benef_stmt = $conn->prepare("
                INSERT INTO trust_beneficiaries
                (trust_application_id, member_id, beneficiary_name, relationship, allocation_percentage)
                VALUES (?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($member_names); $i++) {
                $name = trim((string)($member_names[$i] ?? ''));
                if ($name === '') continue;
                $surname = trim((string)($member_surnames[$i] ?? ''));
                $id_no = trim((string)($member_id_nos[$i] ?? ''));
                $deposit = (float)(trim((string)($member_deposits[$i] ?? '0')));

                $member_stmt->bind_param(
                    "isssd",
                    $trust_app_id,
                    $name,
                    $surname,
                    $id_no,
                    $deposit
                );
                if (!$member_stmt->execute()) throw new RuntimeException('Unable to save a trust member.');
                $memberIdByIndex[$i] = (int)$conn->insert_id;
            }
            $member_stmt->close();

            // Insert all beneficiaries
            for ($i = 0; $i < count($beneficiary_names); $i++) {
                $benef_name = trim((string)($beneficiary_names[$i] ?? ''));
                if ($benef_name === '') continue;
                $relationship = trim((string)($beneficiary_relationships[$i] ?? ''));
                $allocation = (float)(trim((string)($beneficiary_allocations[$i] ?? '0')));
                $memberIndex = (int)($beneficiary_member_indexes[$i] ?? 0);
                $beneficiaryMemberId = (int)($memberIdByIndex[$memberIndex] ?? 0);
                if ($beneficiaryMemberId < 1) throw new InvalidArgumentException('A beneficiary must be linked to a valid trust member.');

                $benef_stmt->bind_param(
                    "iissd",
                    $trust_app_id,
                    $beneficiaryMemberId,
                    $benef_name,
                    $relationship,
                    $allocation
                );
                if (!$benef_stmt->execute()) throw new RuntimeException('Unable to save a trust beneficiary.');
            }
            $benef_stmt->close();

            $conn->commit();
            $_SESSION['success_create']['trust'] = 'Trust application created successfully. Member count: ' . $member_count . ', Beneficiary count: ' . $beneficiary_count;
            redirection_logic();

        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
            if ($uploadedDocumentPath !== null && is_file($uploadedDocumentPath)) @unlink($uploadedDocumentPath);
            error_log('CnrByte trust application failed: ' . $e->getMessage());
            $_SESSION['errors_create']['trust'] = 'The trust application could not be saved. Please check the form and try again.';
            redirection_logic();
        }
        exit;
    }

    // Standard banking application for personal/business
    $stmt = $conn->prepare("
        INSERT INTO banking_applications
        (client_id, application_type, applicant_name, account_subtype, savings_plan, identity_no, reg_no, doc_path, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param(
        "isssssss",
        $client_id,
        $application_type,
        $applicant_name,
        $account_subtype,
        $savings_plan,
        $identity_no,
        $reg_no,
        $doc_path
    );
    $stmt->execute();
    $stmt->close();
}
