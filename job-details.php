<?php
include 'config.php';
require_once 'employer/includes/matching.php';

if (!function_exists('hasRequiredSkills')) {
    function hasRequiredSkills(string $jobRequirements, string $employeeSkills): bool
    {
        $jobTokens = array_unique(tokenize(normalizeText($jobRequirements)));
        $employeeTokens = array_unique(tokenize(normalizeText($employeeSkills)));

        if (empty($jobTokens) || empty($employeeTokens)) {
            return false;
        }

        return empty(array_diff($jobTokens, $employeeTokens));
    }
}

if (!function_exists('getEmployeesRequiredMax')) {
    function getEmployeesRequiredMax(?string $employeesRequired): ?int
    {
        $value = trim((string)$employeesRequired);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int)$value;
        }

        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $value, $matches)) {
            return (int)$matches[2];
        }

        if (preg_match('/^(\d+)\s*\+$/', $value, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}

// Function to get document requirements based on job title and requirements
if (!function_exists('getJobDocumentRequirements')) {
    // Helper function to get Administrative/Office document requirements
    function getAdminOfficeDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'diploma' => ['required' => true, 'label' => 'Diploma'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
            'skills' => ['required' => true, 'label' => 'Training Certificates'],
            'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
            'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
            'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
        ];
    }
    
    // Helper function to get Customer Service / BPO document requirements
    function getCustomerServiceBPODocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_enrollment' => ['required' => true, 'label' => 'Certificate of Enrollment'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training Certificates'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID Photo']
        ];
    }
    
    // Helper function to get Education document requirements
    function getEducationDocuments(): array {
        return [
            'application_letter' => ['required' => true, 'label' => 'Application Letter'],
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'diploma' => ['required' => true, 'label' => 'Diploma'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation (if applicable)'],
            'professional_license' => ['required' => true, 'label' => 'Professional License (LET / PRC ID, if licensed teacher)'],
            'certificate_of_eligibility' => ['required' => true, 'label' => 'Certificate of Eligibility (if applicable)'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment / Service Record (for experienced teachers)'],
            'skills' => ['required' => true, 'label' => 'Training and Seminar Certificates (Teaching, Classroom Management, CPD, etc.)'],
            'teaching_demonstration_plan' => ['required' => true, 'label' => 'Teaching Demonstration Plan / Lesson Plan'],
            'teaching_portfolio' => ['required' => true, 'label' => 'Teaching Portfolio (if required)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID Photos (white background)'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters (School Head / Supervisor)']
        ];
    }
    
    // Helper function to get Engineering document requirements
    function getEngineeringDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'certificate_of_eligibility' => ['required' => true, 'label' => 'Certificate of Eligibility'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio of Projects / Engineering Design Samples'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID Photos'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Information Technology (IT) document requirements
    function getITDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional Certificates'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Project Samples / GitHub links'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Finance / Accounting document requirements
    function getFinanceAccountingDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Work Samples'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Healthcare / Medical document requirements
    function getHealthcareMedicalDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'certificate_of_eligibility' => ['required' => true, 'label' => 'Certificate of Eligibility'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Clinical Logbook / Case Studies'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate / Health Clearance'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID Photos'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Human Resources (HR) document requirements
    function getHRDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional Certificates'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Work Samples'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID Photos'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Manufacturing / Production document requirements
    function getManufacturingProductionDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Logistics / Warehouse / Supply Chain document requirements
    function getLogisticsWarehouseSupplyChainDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Marketing / Sales document requirements
    function getMarketingSalesDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Work Samples'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Creative / Media / Design document requirements
    function getCreativeMediaDesignDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Creative Works'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Construction / Infrastructure document requirements
    function getConstructionInfrastructureDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Project Records'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Food / Hospitality / Tourism document requirements
    function getFoodHospitalityTourismDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Retail / Sales Operations document requirements
    function getRetailSalesOperationsDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Transportation document requirements
    function getTransportationDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional Driver\'s License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Law Enforcement / Criminology document requirements
    function getLawEnforcementCriminologyDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'certificate_of_eligibility' => ['required' => true, 'label' => 'Professional Eligibility'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'physical_fitness_certificate' => ['required' => true, 'label' => 'Physical Fitness Certificate'],
            'drug_test_result' => ['required' => true, 'label' => 'Drug Test Result'],
            'psychological_examination_result' => ['required' => true, 'label' => 'Psychological Examination Result'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Security Services document requirements
    function getSecurityServicesDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Security Training Certificates'],
            'professional_license' => ['required' => true, 'label' => 'License to Exercise Security Profession'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'drug_test_result' => ['required' => true, 'label' => 'Drug Test Result'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Skilled / Technical (TESDA) document requirements
    function getSkilledTechnicalTesdaDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Agriculture / Fisheries document requirements
    function getAgricultureFisheriesDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Freelance / Online / Remote document requirements
    function getFreelanceOnlineRemoteDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'skills' => ['required' => true, 'label' => 'Professional / Skills Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Work Samples'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Legal / Government / Public Service document requirements
    function getLegalGovernmentPublicServiceDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates (Law, Public Administration, Governance, Leadership, etc.)'],
            'portfolio' => ['required' => false, 'label' => 'Legal / Government Portfolio / Case Files / Projects (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Maritime / Aviation / Transport Specialized document requirements
    function getMaritimeAviationTransportSpecializedDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Science / Research / Environment document requirements
    function getScienceResearchEnvironmentDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Research Portfolio'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Arts / Entertainment / Culture document requirements
    function getArtsEntertainmentCultureDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Work Samples'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Religion / NGO / Development / Cooperative document requirements
    function getReligionNgoDevelopmentCooperativeDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Project Reports / Program Documentation'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Special / Rare Jobs document requirements
    function getSpecialRareJobsDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => true, 'label' => 'Portfolio / Work Samples'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Utilities / Public Services document requirements
    function getUtilitiesPublicServicesDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Telecommunications document requirements
    function getTelecommunicationsDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples / Projects (if applicable)'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Mining / Geology document requirements
    function getMiningGeologyDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Project Reports / Field Studies (if applicable)'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Oil / Gas / Energy document requirements
    function getOilGasEnergyDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Project Reports / Field Experience (if applicable)'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Chemical / Industrial document requirements
    function getChemicalIndustrialDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Project Reports / Lab Work (if applicable)'],
            'tesda_nc_certificate' => ['required' => true, 'label' => 'TESDA NC Certificate'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Allied Health / Special Education / Therapy document requirements
    function getAlliedHealthSpecialEducationTherapyDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'certificate_of_eligibility' => ['required' => true, 'label' => 'Certificate of Eligibility'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Case Studies / Patient Reports (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Sports / Fitness / Recreation document requirements
    function getSportsFitnessRecreationDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples / Achievements (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Fashion / Apparel / Beauty document requirements
    function getFashionApparelBeautyDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Home / Personal Services document requirements
    function getHomePersonalServicesDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Insurance / Risk / Banking document requirements
    function getInsuranceRiskBankingDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples / Reports (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Micro Jobs / Informal / Daily Wage document requirements
    function getMicroJobsInformalDailyWageDocuments(): array {
        return [
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Real Estate / Property document requirements
    function getRealEstatePropertyDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID (any one)'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    // Helper function to get Entrepreneurship / Business / Corporate document requirements
    function getEntrepreneurshipBusinessCorporateDocuments(): array {
        return [
            'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
            'education' => ['required' => true, 'label' => 'Transcript of Records'],
            'certificate_of_graduation' => ['required' => true, 'label' => 'Certificate of Graduation'],
            'professional_license' => ['required' => true, 'label' => 'Professional License'],
            'work_experience' => ['required' => true, 'label' => 'Certificate of Employment'],
            'skills' => ['required' => true, 'label' => 'Training / Seminar Certificates'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples / Business Plan (if applicable)'],
            'id_document' => ['required' => true, 'label' => 'Valid Government ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
            'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
            'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
            'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
            'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate (PSA)'],
            'id_photo' => ['required' => true, 'label' => '2x2 or Passport-size ID'],
            'recommendation_letters' => ['required' => true, 'label' => 'Recommendation Letters']
        ];
    }
    
    function getJobDocumentRequirements(string $jobTitle, string $jobRequirements = ''): array
    {
        $title_lower = strtolower(trim($jobTitle));
        $requirements_lower = strtolower(trim($jobRequirements));
        $requirements_value = trim($jobRequirements);
        
        // Map required skills to document requirements
        $skillToDocuments = [
            // Technical/IT Skills
            'Programming (Java, Python, C#)' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / Code Samples']],
            'HTML/CSS/JS' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / Web Development Samples']],
            'Front-End/Back-End' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / Development Projects']],
            'CAD' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / Design Samples']],
            'Technical Drawing' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / Technical Drawings']],
            'E-Learning' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / E-Learning Content']],
            'Curriculum Development' => ['portfolio' => ['required' => true, 'label' => 'Portfolio / Curriculum Work']],
            
            // Certification/License Skills
            'PRC License' => ['skills' => ['required' => true, 'label' => 'PRC License']],
            'Teaching Certification' => ['skills' => ['required' => true, 'label' => 'Teaching Certification']],
            'Counseling Certification' => ['skills' => ['required' => true, 'label' => 'Counseling Certification']],
            'Training Certification' => ['skills' => ['required' => true, 'label' => 'Training Certification']],
            
            // Experience-based Skills
            'Leadership' => ['work_experience' => ['required' => true, 'label' => 'Proof of Leadership Experience']],
            'Supervision' => ['work_experience' => ['required' => true, 'label' => 'Proof of Supervisory Experience']],
            'Team Management' => ['work_experience' => ['required' => true, 'label' => 'Proof of Team Management Experience']],
            'Project Management' => ['work_experience' => ['required' => true, 'label' => 'Proof of Project Management Experience']],
            'Program Management' => ['work_experience' => ['required' => true, 'label' => 'Proof of Program Management Experience']],
            'School Leadership' => ['work_experience' => ['required' => true, 'label' => 'Proof of School Leadership Experience']],
            'School Administration' => ['work_experience' => ['required' => true, 'label' => 'Proof of School Administration Experience']],
            'Call Center Experience' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)']
            ],
            'Customer Service Experience' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)']
            ],
            // Customer Service / BPO Skills - All require full document set
            'Verbal & written communication' => getCustomerServiceBPODocuments(),
            'Problem-solving & conflict resolution' => getCustomerServiceBPODocuments(),
            'Active listening & empathy' => getCustomerServiceBPODocuments(),
            'Basic computer literacy' => getCustomerServiceBPODocuments(),
            'Multitasking & time management' => getCustomerServiceBPODocuments(),
            'CRM software knowledge' => getCustomerServiceBPODocuments(),
            'CRM software knowledge (optional)' => getCustomerServiceBPODocuments(),
            'Phone etiquette & clear communication' => getCustomerServiceBPODocuments(),
            'Script adherence & call handling' => getCustomerServiceBPODocuments(),
            'Customer support & problem resolution' => getCustomerServiceBPODocuments(),
            'Typing & data entry during calls' => getCustomerServiceBPODocuments(),
            'Stress management' => getCustomerServiceBPODocuments(),
            'Customer relationship management' => getCustomerServiceBPODocuments(),
            'Technical troubleshooting (if product-based)' => getCustomerServiceBPODocuments(),
            'Communication & interpersonal skills' => getCustomerServiceBPODocuments(),
            'Product knowledge' => getCustomerServiceBPODocuments(),
            'CRM software proficiency' => getCustomerServiceBPODocuments(),
            'Technical troubleshooting (hardware/software)' => getCustomerServiceBPODocuments(),
            'Ticketing system management' => getCustomerServiceBPODocuments(),
            'Communication & guidance' => getCustomerServiceBPODocuments(),
            'Problem-solving & escalation handling' => getCustomerServiceBPODocuments(),
            'Documentation & reporting' => getCustomerServiceBPODocuments(),
            'Coordinating customer service tasks' => getCustomerServiceBPODocuments(),
            'Communication & follow-ups' => getCustomerServiceBPODocuments(),
            'Scheduling & task management' => getCustomerServiceBPODocuments(),
            'Reporting & data tracking' => getCustomerServiceBPODocuments(),
            'Problem-solving & escalation management' => getCustomerServiceBPODocuments(),
            'Technical troubleshooting & diagnostics' => getCustomerServiceBPODocuments(),
            'Product/service expertise' => getCustomerServiceBPODocuments(),
            'Communication & active listening' => getCustomerServiceBPODocuments(),
            'Patience & empathy' => getCustomerServiceBPODocuments(),
            'Knowledge of support tools & ticketing systems' => getCustomerServiceBPODocuments(),
            'IT support & troubleshooting' => getCustomerServiceBPODocuments(),
            'Ticketing & incident management' => getCustomerServiceBPODocuments(),
            'Communication & problem documentation' => getCustomerServiceBPODocuments(),
            'Prioritization & multitasking' => getCustomerServiceBPODocuments(),
            'Knowledge of systems & networks' => getCustomerServiceBPODocuments(),
            'Account management & client coordination' => getCustomerServiceBPODocuments(),
            'Problem-solving & resolution' => getCustomerServiceBPODocuments(),
            'Communication & relationship-building' => getCustomerServiceBPODocuments(),
            'Reporting & documentation' => getCustomerServiceBPODocuments(),
            'CRM & database proficiency' => getCustomerServiceBPODocuments(),
            'Team leadership & coaching' => getCustomerServiceBPODocuments(),
            'Performance monitoring & reporting' => getCustomerServiceBPODocuments(),
            'Conflict resolution & motivation' => getCustomerServiceBPODocuments(),
            'Scheduling & resource management' => getCustomerServiceBPODocuments(),
            'Communication & escalation handling' => getCustomerServiceBPODocuments(),
            'Customer engagement & support' => getCustomerServiceBPODocuments(),
            'Feedback collection & analysis' => getCustomerServiceBPODocuments(),
            'Communication & empathy' => getCustomerServiceBPODocuments(),
            'Problem-solving & follow-ups' => getCustomerServiceBPODocuments(),
            'Product/service knowledge' => getCustomerServiceBPODocuments(),
            'Training & onboarding new hires' => getCustomerServiceBPODocuments(),
            'Presentation & communication skills' => getCustomerServiceBPODocuments(),
            'Knowledge of policies & procedures' => getCustomerServiceBPODocuments(),
            'Coaching & mentoring' => getCustomerServiceBPODocuments(),
            'Feedback & evaluation skills' => getCustomerServiceBPODocuments(),
            'Written communication & typing speed' => getCustomerServiceBPODocuments(),
            'Multitasking & handling multiple chats' => getCustomerServiceBPODocuments(),
            'Customer problem-solving' => getCustomerServiceBPODocuments(),
            'Knowledge of product/service' => getCustomerServiceBPODocuments(),
            'CRM/chat software proficiency' => getCustomerServiceBPODocuments(),
            'Professional written communication' => getCustomerServiceBPODocuments(),
            'Typing & grammar accuracy' => getCustomerServiceBPODocuments(),
            'Email ticket management' => getCustomerServiceBPODocuments(),
            'Problem-solving & follow-up' => getCustomerServiceBPODocuments(),
            'CRM/email support software knowledge' => getCustomerServiceBPODocuments(),
            'Handling complex customer issues' => getCustomerServiceBPODocuments(),
            'Problem-solving & critical thinking' => getCustomerServiceBPODocuments(),
            'Communication & negotiation' => getCustomerServiceBPODocuments(),
            'Decision-making & discretion' => getCustomerServiceBPODocuments(),
            'Knowledge of policies & procedures' => getCustomerServiceBPODocuments(),
            'Monitoring calls/chats for quality' => getCustomerServiceBPODocuments(),
            'Analytical & observation skills' => getCustomerServiceBPODocuments(),
            'Communication & reporting' => getCustomerServiceBPODocuments(),
            'Feedback & coaching' => getCustomerServiceBPODocuments(),
            'Process improvement' => getCustomerServiceBPODocuments(),
            'Persuasion & negotiation' => getCustomerServiceBPODocuments(),
            'Relationship management' => getCustomerServiceBPODocuments(),
            'Problem-solving & empathy' => getCustomerServiceBPODocuments(),
            'Communication & follow-up' => getCustomerServiceBPODocuments(),
            'Remote communication & tech skills' => getCustomerServiceBPODocuments(),
            'Problem-solving & multitasking' => getCustomerServiceBPODocuments(),
            'CRM & virtual collaboration tools' => getCustomerServiceBPODocuments(),
            'Time management & self-discipline' => getCustomerServiceBPODocuments(),
            'Customer support & empathy' => getCustomerServiceBPODocuments(),
            'Sales & upselling techniques' => getCustomerServiceBPODocuments(),
            'CRM & lead tracking' => getCustomerServiceBPODocuments(),
            'Customer service & problem-solving' => getCustomerServiceBPODocuments(),
            'Team supervision & leadership' => getCustomerServiceBPODocuments(),
            'Performance monitoring & coaching' => getCustomerServiceBPODocuments(),
            'Escalation management' => getCustomerServiceBPODocuments(),
            'Scheduling & workload allocation' => getCustomerServiceBPODocuments(),
            'Communication & conflict resolution' => getCustomerServiceBPODocuments(),
            'Escalation Handling' => ['work_experience' => ['required' => true, 'label' => 'Proof of Experience Handling Escalated Issues']],
            'Consulting Experience' => ['work_experience' => ['required' => true, 'label' => 'Proof of Consulting/Advisory Experience']],
            'Facilitation Experience' => ['work_experience' => ['required' => true, 'label' => 'Proof of Facilitation/Training Experience']],
            'Coordination Experience' => ['work_experience' => ['required' => true, 'label' => 'Proof of Coordination Experience']],
            // Administrative / Office Skills - All documents required
            'Office management & coordination' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Scheduling & calendar management' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Record keeping & filing systems' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Communication (verbal & written)' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'MS Office / Google Workspace proficiency' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Problem-solving & decision-making' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Basic HR/admin processes' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            // Administrative/Office skills - all require full document set
            'Supporting senior executives' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Calendar & travel management' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Confidentiality & discretion' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Meeting & event coordination' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Strong written & verbal communication' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Document preparation (reports, presentations)' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Multitasking & prioritization' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Office workflow coordination' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Task & project tracking' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Scheduling & resource allocation' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Communication & liaison skills' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Record keeping & reporting' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            'Problem-solving & process improvement' => [
                'cover_letter' => ['required' => true, 'label' => 'Cover Letter'],
                'personal_data_sheet' => ['required' => true, 'label' => 'Personal Data Sheet'],
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'barangay_clearance' => ['required' => true, 'label' => 'Barangay Clearance'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance'],
                'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['required' => true, 'label' => 'Medical Certificate'],
                'education' => ['required' => true, 'label' => 'Transcript of Records (TOR)'],
                'diploma' => ['required' => true, 'label' => 'Diploma'],
                'work_experience' => ['required' => true, 'label' => 'Certificate of Employment (if applicable)'],
                'skills' => ['required' => true, 'label' => 'Training Certificates'],
                'id_document' => ['required' => true, 'label' => 'Valid Government-Issued ID'],
                'id_photo' => ['required' => true, 'label' => '2x2 ID Photo'],
                'good_moral_character' => ['required' => true, 'label' => 'Certificate of Good Moral Character']
            ],
            // Additional Administrative/Office skills - all require full document set
            'Fast & accurate typing' => getAdminOfficeDocuments(),
            'Attention to detail' => getAdminOfficeDocuments(),
            'Basic computer skills (Excel, databases)' => getAdminOfficeDocuments(),
            'Data verification & quality control' => getAdminOfficeDocuments(),
            'Time management' => getAdminOfficeDocuments(),
            'Team management & leadership' => getAdminOfficeDocuments(),
            'Office operations & logistics' => getAdminOfficeDocuments(),
            'Budgeting & procurement' => getAdminOfficeDocuments(),
            'Policy & procedure implementation' => getAdminOfficeDocuments(),
            'Communication & problem-solving' => getAdminOfficeDocuments(),
            'Customer service & friendliness' => getAdminOfficeDocuments(),
            'Phone etiquette & call handling' => getAdminOfficeDocuments(),
            'Visitor management' => getAdminOfficeDocuments(),
            'Basic administrative support' => getAdminOfficeDocuments(),
            'Scheduling appointments' => getAdminOfficeDocuments(),
            'Multitasking' => getAdminOfficeDocuments(),
            'Executive support' => getAdminOfficeDocuments(),
            'Travel & meeting coordination' => getAdminOfficeDocuments(),
            'Task prioritization' => getAdminOfficeDocuments(),
            'Communication & interpersonal skills' => getAdminOfficeDocuments(),
            'Office administration & operations' => getAdminOfficeDocuments(),
            'Record keeping & document management' => getAdminOfficeDocuments(),
            'Reporting & correspondence' => getAdminOfficeDocuments(),
            'Policy & procedure adherence' => getAdminOfficeDocuments(),
            'Coordination with staff & departments' => getAdminOfficeDocuments(),
            'File management & archiving' => getAdminOfficeDocuments(),
            'Data accuracy & indexing' => getAdminOfficeDocuments(),
            'Retrieval of documents' => getAdminOfficeDocuments(),
            'Basic computer skills' => getAdminOfficeDocuments(),
            'Workflow & process support' => getAdminOfficeDocuments(),
            'Reporting & documentation' => getAdminOfficeDocuments(),
            'Problem-solving & organization' => getAdminOfficeDocuments(),
            'Communication skills' => getAdminOfficeDocuments(),
            'Typing & document preparation' => getAdminOfficeDocuments(),
            'Scheduling & correspondence' => getAdminOfficeDocuments(),
            'Filing & record keeping' => getAdminOfficeDocuments(),
            'Communication & coordination' => getAdminOfficeDocuments(),
            'Reception & phone handling' => getAdminOfficeDocuments(),
            'Customer service & reception' => getAdminOfficeDocuments(),
            'Phone & email handling' => getAdminOfficeDocuments(),
            'Visitor & appointment management' => getAdminOfficeDocuments(),
            'Basic administrative tasks' => getAdminOfficeDocuments(),
            'Organization & multitasking' => getAdminOfficeDocuments(),
            'Executive support & correspondence' => getAdminOfficeDocuments(),
            'Scheduling & meeting coordination' => getAdminOfficeDocuments(),
            'Document drafting & report preparation' => getAdminOfficeDocuments(),
            'Event planning & coordination' => getAdminOfficeDocuments(),
            'Data entry & document handling' => getAdminOfficeDocuments(),
            'Mail & correspondence management' => getAdminOfficeDocuments(),
            'Organizational skills' => getAdminOfficeDocuments(),
            'Document sorting & filing' => getAdminOfficeDocuments(),
            'Indexing & retrieval' => getAdminOfficeDocuments(),
            'Accuracy & attention to detail' => getAdminOfficeDocuments(),
            'Data entry' => getAdminOfficeDocuments(),
            'Calendar & appointment management' => getAdminOfficeDocuments(),
            'Time management & multitasking' => getAdminOfficeDocuments(),
            'Office operations & facilities management' => getAdminOfficeDocuments(),
            'Vendor & supplier coordination' => getAdminOfficeDocuments(),
            'Team supervision & support' => getAdminOfficeDocuments(),
            'Problem-solving & planning' => getAdminOfficeDocuments(),
            'Document creation & management' => getAdminOfficeDocuments(),
            'Filing & archiving' => getAdminOfficeDocuments(),
            'Compliance with standards' => getAdminOfficeDocuments(),
            'Record accuracy & verification' => getAdminOfficeDocuments(),
            'Administrative support & coordination' => getAdminOfficeDocuments(),
            'Document handling & filing' => getAdminOfficeDocuments(),
            'Scheduling & office logistics' => getAdminOfficeDocuments(),
            'Team leadership & supervision' => getAdminOfficeDocuments(),
            'Office workflow management' => getAdminOfficeDocuments(),
            'Task delegation & monitoring' => getAdminOfficeDocuments(),
            'Communication & conflict resolution' => getAdminOfficeDocuments(),
            
            // Education Skills - All require full document set
            'Lesson planning & curriculum delivery' => getEducationDocuments(),
            'Classroom management' => getEducationDocuments(),
            'Communication & presentation' => getEducationDocuments(),
            'Assessment & evaluation' => getEducationDocuments(),
            'Subject matter expertise' => getEducationDocuments(),
            'Adaptability & creativity' => getEducationDocuments(),
            'Student guidance & support' => getEducationDocuments(),
            'Active listening & empathy' => getEducationDocuments(),
            'Career & academic advising' => getEducationDocuments(),
            'Conflict resolution & problem-solving' => getEducationDocuments(),
            'Confidentiality & ethics' => getEducationDocuments(),
            'Record keeping & reporting' => getEducationDocuments(),
            'Curriculum planning & coordination' => getEducationDocuments(),
            'Teacher support & training' => getEducationDocuments(),
            'Scheduling & resource allocation' => getEducationDocuments(),
            'Data tracking & reporting' => getEducationDocuments(),
            'Communication & leadership' => getEducationDocuments(),
            'One-on-one instruction & mentoring' => getEducationDocuments(),
            'Patience & adaptability' => getEducationDocuments(),
            'Communication & explanation skills' => getEducationDocuments(),
            'Progress tracking & assessment' => getEducationDocuments(),
            'School leadership & administration' => getEducationDocuments(),
            'Staff supervision & development' => getEducationDocuments(),
            'Policy implementation' => getEducationDocuments(),
            'Communication & conflict resolution' => getEducationDocuments(),
            'Strategic planning & decision-making' => getEducationDocuments(),
            'Budget & resource management' => getEducationDocuments(),
            'Cataloging & information management' => getEducationDocuments(),
            'Research assistance & literacy promotion' => getEducationDocuments(),
            'Library software & database knowledge' => getEducationDocuments(),
            'Organizational & administrative skills' => getEducationDocuments(),
            'Customer service (students & staff)' => getEducationDocuments(),
            'Individualized Education Program (IEP) design & implementation' => getEducationDocuments(),
            'Inclusive teaching strategies' => getEducationDocuments(),
            'Patience & empathy' => getEducationDocuments(),
            'Behavior management' => getEducationDocuments(),
            'Communication with parents & staff' => getEducationDocuments(),
            'Curriculum design & lesson planning' => getEducationDocuments(),
            'Educational standards knowledge' => getEducationDocuments(),
            'Content creation & assessment design' => getEducationDocuments(),
            'Analytical & research skills' => getEducationDocuments(),
            'Collaboration with educators' => getEducationDocuments(),
            'Program planning & execution' => getEducationDocuments(),
            'Team management & coordination' => getEducationDocuments(),
            'Budgeting & resource management' => getEducationDocuments(),
            'Reporting & evaluation' => getEducationDocuments(),
            'Communication & stakeholder engagement' => getEducationDocuments(),
            'Subject matter expertise' => getEducationDocuments(),
            'Lesson preparation & delivery' => getEducationDocuments(),
            'Academic research & publication' => getEducationDocuments(),
            'Assessment & grading' => getEducationDocuments(),
            'Public speaking & presentation skills' => getEducationDocuments(),
            'Advanced subject knowledge' => getEducationDocuments(),
            'Lesson planning & teaching' => getEducationDocuments(),
            'Student assessment & mentoring' => getEducationDocuments(),
            'Research & academic writing' => getEducationDocuments(),
            'Communication & critical thinking' => getEducationDocuments(),
            'Early childhood education principles' => getEducationDocuments(),
            'Classroom & behavior management' => getEducationDocuments(),
            'Creativity & play-based learning' => getEducationDocuments(),
            'Communication with parents' => getEducationDocuments(),
            'Patience & nurturing' => getEducationDocuments(),
            'Classroom support' => getEducationDocuments(),
            'Student supervision' => getEducationDocuments(),
            'Lesson preparation & material support' => getEducationDocuments(),
            'Communication & collaboration with teachers' => getEducationDocuments(),
            'Patience & adaptability' => getEducationDocuments(),
            'Curriculum & e-learning design' => getEducationDocuments(),
            'Instructional technology proficiency' => getEducationDocuments(),
            'Content development & assessment creation' => getEducationDocuments(),
            'Analytical & research skills' => getEducationDocuments(),
            'Project management' => getEducationDocuments(),
            'Workshop & session facilitation' => getEducationDocuments(),
            'Communication & engagement skills' => getEducationDocuments(),
            'Lesson delivery & activity planning' => getEducationDocuments(),
            'Assessment & feedback' => getEducationDocuments(),
            'Adaptability & problem-solving' => getEducationDocuments(),
            'Educational assessment & advising' => getEducationDocuments(),
            'Program evaluation & improvement' => getEducationDocuments(),
            'Research & analytical skills' => getEducationDocuments(),
            'Communication & presentation' => getEducationDocuments(),
            'Stakeholder management' => getEducationDocuments(),
            'Classroom management' => getEducationDocuments(),
            'Lesson planning & delivery' => getEducationDocuments(),
            'Student guidance & support' => getEducationDocuments(),
            'Communication with parents & staff' => getEducationDocuments(),
            'Monitoring student progress' => getEducationDocuments(),
            'School operations & management' => getEducationDocuments(),
            'Staff supervision & coordination' => getEducationDocuments(),
            'Policy implementation' => getEducationDocuments(),
            'Budgeting & scheduling' => getEducationDocuments(),
            'Communication & leadership' => getEducationDocuments(),
            'Academic & career guidance' => getEducationDocuments(),
            'Emotional support & counseling' => getEducationDocuments(),
            'Active listening & problem-solving' => getEducationDocuments(),
            'Confidentiality & ethics' => getEducationDocuments(),
            'Student record keeping' => getEducationDocuments(),
            'Academic planning & course selection guidance' => getEducationDocuments(),
            'Student mentorship & support' => getEducationDocuments(),
            'Communication & problem-solving' => getEducationDocuments(),
            'Record keeping & reporting' => getEducationDocuments(),
            'Knowledge of academic policies' => getEducationDocuments(),
            // Legacy Education Skills (keep for backward compatibility)
            'Subject Knowledge' => getEducationDocuments(),
            'Subject Expertise' => getEducationDocuments(),
            'Academic Knowledge' => getEducationDocuments(),
            'Research' => getEducationDocuments(),
            'Master\'s Degree' => getEducationDocuments(),
            // Engineering Skills - All require full document set
            'Structural analysis & design' => getEngineeringDocuments(),
            'Project planning & management' => getEngineeringDocuments(),
            'Surveying & site inspection' => getEngineeringDocuments(),
            'Knowledge of building codes & standards' => getEngineeringDocuments(),
            'AutoCAD / Civil 3D proficiency' => getEngineeringDocuments(),
            'Problem-solving & analytical thinking' => getEngineeringDocuments(),
            'Mechanical design & analysis' => getEngineeringDocuments(),
            'CAD software (SolidWorks, AutoCAD, CATIA)' => getEngineeringDocuments(),
            'Thermodynamics & fluid mechanics' => getEngineeringDocuments(),
            'Manufacturing processes' => getEngineeringDocuments(),
            'Troubleshooting & problem-solving' => getEngineeringDocuments(),
            'Project management' => getEngineeringDocuments(),
            'Circuit design & analysis' => getEngineeringDocuments(),
            'Power systems & electronics' => getEngineeringDocuments(),
            'PLC & control systems' => getEngineeringDocuments(),
            'Troubleshooting electrical systems' => getEngineeringDocuments(),
            'Technical documentation & compliance' => getEngineeringDocuments(),
            'Analytical & problem-solving skills' => getEngineeringDocuments(),
            'Project planning & scheduling' => getEngineeringDocuments(),
            'Budget & resource management' => getEngineeringDocuments(),
            'Coordination with teams & stakeholders' => getEngineeringDocuments(),
            'Risk management & problem-solving' => getEngineeringDocuments(),
            'Technical understanding of project field' => getEngineeringDocuments(),
            'Structural analysis & design (buildings, bridges)' => getEngineeringDocuments(),
            'Knowledge of codes & safety standards' => getEngineeringDocuments(),
            'CAD / structural design software' => getEngineeringDocuments(),
            'Material science & stress analysis' => getEngineeringDocuments(),
            'Problem-solving & critical thinking' => getEngineeringDocuments(),
            'Process design & optimization' => getEngineeringDocuments(),
            'Chemical reaction analysis & safety' => getEngineeringDocuments(),
            'Laboratory & experimental skills' => getEngineeringDocuments(),
            'Knowledge of chemical regulations & compliance' => getEngineeringDocuments(),
            'Process optimization & workflow analysis' => getEngineeringDocuments(),
            'Lean manufacturing & Six Sigma' => getEngineeringDocuments(),
            'Productivity improvement & efficiency' => getEngineeringDocuments(),
            'Data analysis & reporting' => getEngineeringDocuments(),
            'Project management & teamwork' => getEngineeringDocuments(),
            'Production & manufacturing knowledge' => getEngineeringDocuments(),
            'Safety & compliance regulations' => getEngineeringDocuments(),
            'Problem-solving & troubleshooting' => getEngineeringDocuments(),
            'Data analysis & process monitoring' => getEngineeringDocuments(),
            'Quality assurance & control' => getEngineeringDocuments(),
            'ISO standards & regulatory compliance' => getEngineeringDocuments(),
            'Testing & inspection' => getEngineeringDocuments(),
            'Problem-solving & root cause analysis' => getEngineeringDocuments(),
            'Documentation & reporting' => getEngineeringDocuments(),
            'Product / mechanical / electrical design' => getEngineeringDocuments(),
            'CAD / CAM / SolidWorks / AutoCAD' => getEngineeringDocuments(),
            'Prototyping & modeling' => getEngineeringDocuments(),
            'Material selection & analysis' => getEngineeringDocuments(),
            'Problem-solving & creativity' => getEngineeringDocuments(),
            'Equipment maintenance & troubleshooting' => getEngineeringDocuments(),
            'Preventive & corrective maintenance planning' => getEngineeringDocuments(),
            'Mechanical / electrical skills depending on field' => getEngineeringDocuments(),
            'Technical documentation' => getEngineeringDocuments(),
            'Problem-solving & safety compliance' => getEngineeringDocuments(),
            'On-site engineering & inspection' => getEngineeringDocuments(),
            'Project coordination & reporting' => getEngineeringDocuments(),
            'Technical knowledge of field (civil, mechanical, electrical)' => getEngineeringDocuments(),
            'Communication & adaptability' => getEngineeringDocuments(),
            'Systems design & integration' => getEngineeringDocuments(),
            'Requirement analysis & documentation' => getEngineeringDocuments(),
            'Problem-solving & analytical thinking' => getEngineeringDocuments(),
            'Knowledge of IT / control systems' => getEngineeringDocuments(),
            'Testing & validation' => getEngineeringDocuments(),
            'Technical drawing & documentation' => getEngineeringDocuments(),
            'Equipment maintenance & testing' => getEngineeringDocuments(),
            'Data collection & reporting' => getEngineeringDocuments(),
            'Hands-on problem-solving' => getEngineeringDocuments(),
            'Technical software proficiency' => getEngineeringDocuments(),
            'PLC programming & control systems' => getEngineeringDocuments(),
            'Robotics & automation design' => getEngineeringDocuments(),
            'Process optimization' => getEngineeringDocuments(),
            'Product concept development & prototyping' => getEngineeringDocuments(),
            'CAD / 3D modeling & simulation' => getEngineeringDocuments(),
            'Material selection & manufacturing knowledge' => getEngineeringDocuments(),
            'Project coordination' => getEngineeringDocuments(),
            'PLC / SCADA / DCS systems' => getEngineeringDocuments(),
            'Automation & process control' => getEngineeringDocuments(),
            'Systems integration' => getEngineeringDocuments(),
            'Environmental assessment & compliance' => getEngineeringDocuments(),
            'Pollution control & sustainability solutions' => getEngineeringDocuments(),
            'Waste management & remediation' => getEngineeringDocuments(),
            'Regulatory knowledge & reporting' => getEngineeringDocuments(),
            'Occupational safety & hazard assessment' => getEngineeringDocuments(),
            'Risk management & compliance' => getEngineeringDocuments(),
            'Safety audits & inspections' => getEngineeringDocuments(),
            'Emergency response planning' => getEngineeringDocuments(),
            'Communication & training' => getEngineeringDocuments(),
            'Equipment reliability & failure analysis' => getEngineeringDocuments(),
            'Predictive & preventive maintenance' => getEngineeringDocuments(),
            'Data analysis & performance metrics' => getEngineeringDocuments(),
            'Root cause analysis & problem-solving' => getEngineeringDocuments(),
            'Technical documentation & reporting' => getEngineeringDocuments(),
            // Information Technology (IT) Skills - All require full document set
            'Programming (Java, Python, C#)' => getITDocuments(),
            'HTML/CSS/JS' => getITDocuments(),
            'Front-End/Back-End' => getITDocuments(),
            'Networking' => getITDocuments(),
            'Troubleshooting' => getITDocuments(),
            'SQL' => getITDocuments(),
            'Network Security' => getITDocuments(),
            'Cloud Platforms (AWS/Azure)' => getITDocuments(),
            'CI/CD' => getITDocuments(),
            'Mobile Platforms (iOS/Android)' => getITDocuments(),
            'ETL' => getITDocuments(),
            'Analytical Thinking' => getITDocuments(),
            'Requirements Gathering' => getITDocuments(),
            'Risk Assessment' => getITDocuments(),
            'Programming' => getITDocuments(),
            'Problem-Solving' => getITDocuments(),
            'Debugging' => getITDocuments(),
            'Git' => getITDocuments(),
            'Communication' => getITDocuments(),
            'Security' => getITDocuments(),
            'Configuration' => getITDocuments(),
            'Customer Service' => getITDocuments(),
            'Hardware/Software Knowledge' => getITDocuments(),
            'Responsive Design' => getITDocuments(),
            'Documentation' => getITDocuments(),
            'Database Design' => getITDocuments(),
            'Backup & Recovery' => getITDocuments(),
            'Ethical Hacking' => getITDocuments(),
            'Analytical Skills' => getITDocuments(),
            'Incident Response' => getITDocuments(),
            'Deployment' => getITDocuments(),
            'Scripting' => getITDocuments(),
            'Leadership' => getITDocuments(),
            'Project Management' => getITDocuments(),
            'IT Strategy' => getITDocuments(),
            'Code Review' => getITDocuments(),
            'Testing' => getITDocuments(),
            'Version Control' => getITDocuments(),
            'Monitoring' => getITDocuments(),
            'UI/UX' => getITDocuments(),
            'Data Modeling' => getITDocuments(),
            'Python/Scala' => getITDocuments(),
            'Big Data Tools' => getITDocuments(),
            'Firewalls' => getITDocuments(),
            'Intrusion Detection' => getITDocuments(),
            'VPN' => getITDocuments(),
            'Security Policies' => getITDocuments(),
            'Project Planning' => getITDocuments(),
            'Risk Management' => getITDocuments(),
            'Design Principles' => getITDocuments(),
            'Prototyping' => getITDocuments(),
            'User Research' => getITDocuments(),
            'Frameworks' => getITDocuments(),
            'Server-Side Programming' => getITDocuments(),
            'Databases' => getITDocuments(),
            'API Development' => getITDocuments(),
            'Servers' => getITDocuments(),
            'Virtualization' => getITDocuments(),
            'Technical Expertise' => getITDocuments(),
            'Client Management' => getITDocuments(),
            'Compliance' => getITDocuments(),
            'Reporting' => getITDocuments(),
            'IT Knowledge' => getITDocuments(),
            
            // Finance / Accounting Skills - All require full document set
            'Financial reporting & bookkeeping' => getFinanceAccountingDocuments(),
            'General ledger management' => getFinanceAccountingDocuments(),
            'Budgeting & forecasting' => getFinanceAccountingDocuments(),
            'Accounting software (QuickBooks, SAP, Xero)' => getFinanceAccountingDocuments(),
            'Attention to detail & accuracy' => getFinanceAccountingDocuments(),
            'Regulatory compliance' => getFinanceAccountingDocuments(),
            'Financial modeling & analysis' => getFinanceAccountingDocuments(),
            'Data interpretation & reporting' => getFinanceAccountingDocuments(),
            'Excel / spreadsheets & analytics tools' => getFinanceAccountingDocuments(),
            'Business acumen & problem-solving' => getFinanceAccountingDocuments(),
            'Recording financial transactions' => getFinanceAccountingDocuments(),
            'Accounts reconciliation' => getFinanceAccountingDocuments(),
            'Payroll processing' => getFinanceAccountingDocuments(),
            'Accounting software (QuickBooks, MYOB)' => getFinanceAccountingDocuments(),
            'Attention to detail & organization' => getFinanceAccountingDocuments(),
            'Payroll processing & management' => getFinanceAccountingDocuments(),
            'Tax calculations & deductions' => getFinanceAccountingDocuments(),
            'Compliance with labor laws' => getFinanceAccountingDocuments(),
            'Accounting software proficiency' => getFinanceAccountingDocuments(),
            'Confidentiality & accuracy' => getFinanceAccountingDocuments(),
            'Tax preparation & filing' => getFinanceAccountingDocuments(),
            'Knowledge of local & international tax laws' => getFinanceAccountingDocuments(),
            'Tax planning & advisory' => getFinanceAccountingDocuments(),
            'Accounting & ERP software' => getFinanceAccountingDocuments(),
            'Analytical & compliance skills' => getFinanceAccountingDocuments(),
            'Budget planning & monitoring' => getFinanceAccountingDocuments(),
            'Forecasting & variance analysis' => getFinanceAccountingDocuments(),
            'Financial reporting & presentations' => getFinanceAccountingDocuments(),
            'Excel / data analysis' => getFinanceAccountingDocuments(),
            'Communication & decision-making' => getFinanceAccountingDocuments(),
            'Financial audit & control evaluation' => getFinanceAccountingDocuments(),
            'Risk assessment & compliance' => getFinanceAccountingDocuments(),
            'Accounting standards & regulations' => getFinanceAccountingDocuments(),
            'Report preparation & documentation' => getFinanceAccountingDocuments(),
            'Analytical & investigative skills' => getFinanceAccountingDocuments(),
            'Financial planning & strategy' => getFinanceAccountingDocuments(),
            'Team management & leadership' => getFinanceAccountingDocuments(),
            'Financial reporting & analysis' => getFinanceAccountingDocuments(),
            'Regulatory compliance & risk management' => getFinanceAccountingDocuments(),
            'Credit risk assessment & evaluation' => getFinanceAccountingDocuments(),
            'Financial statement analysis' => getFinanceAccountingDocuments(),
            'Loan underwriting & recommendations' => getFinanceAccountingDocuments(),
            'Decision-making & problem-solving' => getFinanceAccountingDocuments(),
            'Communication with stakeholders' => getFinanceAccountingDocuments(),
            'Oversight of accounting operations' => getFinanceAccountingDocuments(),
            'Financial reporting & consolidation' => getFinanceAccountingDocuments(),
            'Budgeting & cash flow management' => getFinanceAccountingDocuments(),
            'Compliance & internal controls' => getFinanceAccountingDocuments(),
            'Leadership & team management' => getFinanceAccountingDocuments(),
            'Cost analysis & allocation' => getFinanceAccountingDocuments(),
            'Budgeting & inventory costing' => getFinanceAccountingDocuments(),
            'Financial reporting & variance analysis' => getFinanceAccountingDocuments(),
            'Accounting software & ERP systems' => getFinanceAccountingDocuments(),
            'Analytical & problem-solving skills' => getFinanceAccountingDocuments(),
            'Cash flow management' => getFinanceAccountingDocuments(),
            'Liquidity & investment tracking' => getFinanceAccountingDocuments(),
            'Risk management & compliance' => getFinanceAccountingDocuments(),
            'Financial modeling & reporting' => getFinanceAccountingDocuments(),
            'Analytical & decision-making skills' => getFinanceAccountingDocuments(),
            'Invoice processing & payment tracking' => getFinanceAccountingDocuments(),
            'Vendor management' => getFinanceAccountingDocuments(),
            'Reconciliation & record keeping' => getFinanceAccountingDocuments(),
            'Billing & collections management' => getFinanceAccountingDocuments(),
            'Customer account monitoring' => getFinanceAccountingDocuments(),
            'Reconciliation & reporting' => getFinanceAccountingDocuments(),
            'Financial reporting & budgeting' => getFinanceAccountingDocuments(),
            'Accounting & bookkeeping' => getFinanceAccountingDocuments(),
            'Compliance & internal controls' => getFinanceAccountingDocuments(),
            'Software proficiency (Excel, ERP)' => getFinanceAccountingDocuments(),
            'Investment research & analysis' => getFinanceAccountingDocuments(),
            'Portfolio management & risk assessment' => getFinanceAccountingDocuments(),
            'Financial modeling & forecasting' => getFinanceAccountingDocuments(),
            'Market & industry knowledge' => getFinanceAccountingDocuments(),
            'Risk assessment & mitigation' => getFinanceAccountingDocuments(),
            'Financial & operational risk analysis' => getFinanceAccountingDocuments(),
            'Compliance & regulatory knowledge' => getFinanceAccountingDocuments(),
            'Reporting & documentation' => getFinanceAccountingDocuments(),
            'Regulatory compliance monitoring' => getFinanceAccountingDocuments(),
            'Policy & procedure implementation' => getFinanceAccountingDocuments(),
            'Audit & reporting' => getFinanceAccountingDocuments(),
            'Risk assessment & management' => getFinanceAccountingDocuments(),
            'Attention to detail & ethics' => getFinanceAccountingDocuments(),
            'Loan processing & evaluation' => getFinanceAccountingDocuments(),
            'Credit analysis & risk assessment' => getFinanceAccountingDocuments(),
            'Customer communication & advisory' => getFinanceAccountingDocuments(),
            'Compliance with lending regulations' => getFinanceAccountingDocuments(),
            'Attention to detail & decision-making' => getFinanceAccountingDocuments(),
            'Fund accounting & reconciliation' => getFinanceAccountingDocuments(),
            'Financial reporting for funds' => getFinanceAccountingDocuments(),
            'NAV calculation & compliance' => getFinanceAccountingDocuments(),
            'Analytical & detail-oriented' => getFinanceAccountingDocuments(),
            'Invoice preparation & billing management' => getFinanceAccountingDocuments(),
            'Accuracy & attention to detail' => getFinanceAccountingDocuments(),
            'Cash flow & liquidity management' => getFinanceAccountingDocuments(),
            'Investment tracking & reporting' => getFinanceAccountingDocuments(),
            'Financial analysis & forecasting' => getFinanceAccountingDocuments(),
            
            // Healthcare / Medical Skills - All require full document set
            'Diagnosis & treatment planning' => getHealthcareMedicalDocuments(),
            'Medical knowledge & clinical skills' => getHealthcareMedicalDocuments(),
            'Patient examination & monitoring' => getHealthcareMedicalDocuments(),
            'Communication & empathy' => getHealthcareMedicalDocuments(),
            'Record keeping & documentation' => getHealthcareMedicalDocuments(),
            'Critical thinking & decision-making' => getHealthcareMedicalDocuments(),
            'Patient care & monitoring' => getHealthcareMedicalDocuments(),
            'Medication administration & documentation' => getHealthcareMedicalDocuments(),
            'Vital signs assessment' => getHealthcareMedicalDocuments(),
            'Patient education & communication' => getHealthcareMedicalDocuments(),
            'Clinical procedures & safety protocols' => getHealthcareMedicalDocuments(),
            'Compassion & teamwork' => getHealthcareMedicalDocuments(),
            'Laboratory testing & analysis' => getHealthcareMedicalDocuments(),
            'Sample collection & processing' => getHealthcareMedicalDocuments(),
            'Equipment operation & calibration' => getHealthcareMedicalDocuments(),
            'Quality control & compliance' => getHealthcareMedicalDocuments(),
            'Data interpretation & reporting' => getHealthcareMedicalDocuments(),
            'Medication dispensing & verification' => getHealthcareMedicalDocuments(),
            'Drug interactions & counseling' => getHealthcareMedicalDocuments(),
            'Prescription review & compliance' => getHealthcareMedicalDocuments(),
            'Inventory management' => getHealthcareMedicalDocuments(),
            'Oral examination & diagnosis' => getHealthcareMedicalDocuments(),
            'Dental procedures & surgery' => getHealthcareMedicalDocuments(),
            'Patient education & preventive care' => getHealthcareMedicalDocuments(),
            'Sterilization & safety protocols' => getHealthcareMedicalDocuments(),
            'Medical imaging & radiography' => getHealthcareMedicalDocuments(),
            'Equipment operation (X-ray, CT, MRI)' => getHealthcareMedicalDocuments(),
            'Patient positioning & safety' => getHealthcareMedicalDocuments(),
            'Image analysis & reporting' => getHealthcareMedicalDocuments(),
            'Compliance with radiation safety' => getHealthcareMedicalDocuments(),
            'Rehabilitation & therapy planning' => getHealthcareMedicalDocuments(),
            'Patient assessment & exercise instruction' => getHealthcareMedicalDocuments(),
            'Manual therapy & mobility improvement' => getHealthcareMedicalDocuments(),
            'Communication & motivation' => getHealthcareMedicalDocuments(),
            'Record keeping & progress tracking' => getHealthcareMedicalDocuments(),
            'Rehabilitation & daily living support' => getHealthcareMedicalDocuments(),
            'Patient assessment & intervention planning' => getHealthcareMedicalDocuments(),
            'Adaptive equipment training' => getHealthcareMedicalDocuments(),
            'Progress documentation' => getHealthcareMedicalDocuments(),
            'Sample collection & preparation' => getHealthcareMedicalDocuments(),
            'Lab testing & analysis' => getHealthcareMedicalDocuments(),
            'Equipment operation & maintenance' => getHealthcareMedicalDocuments(),
            'Data recording & reporting' => getHealthcareMedicalDocuments(),
            'Compliance with safety protocols' => getHealthcareMedicalDocuments(),
            'Prenatal & postnatal care' => getHealthcareMedicalDocuments(),
            'Labor & delivery support' => getHealthcareMedicalDocuments(),
            'Patient education & counseling' => getHealthcareMedicalDocuments(),
            'Emergency response & clinical skills' => getHealthcareMedicalDocuments(),
            'Emergency response & patient assessment' => getHealthcareMedicalDocuments(),
            'Life support & first aid' => getHealthcareMedicalDocuments(),
            'Rapid decision-making & triage' => getHealthcareMedicalDocuments(),
            'Documentation & reporting' => getHealthcareMedicalDocuments(),
            'Nutrition assessment & meal planning' => getHealthcareMedicalDocuments(),
            'Dietary counseling & education' => getHealthcareMedicalDocuments(),
            'Food safety & regulatory compliance' => getHealthcareMedicalDocuments(),
            'Analytical & problem-solving skills' => getHealthcareMedicalDocuments(),
            'Advanced patient assessment & diagnosis' => getHealthcareMedicalDocuments(),
            'Medication prescribing & management' => getHealthcareMedicalDocuments(),
            'Clinical decision-making' => getHealthcareMedicalDocuments(),
            'Collaboration with healthcare teams' => getHealthcareMedicalDocuments(),
            'Anesthesia administration & monitoring' => getHealthcareMedicalDocuments(),
            'Patient evaluation & risk assessment' => getHealthcareMedicalDocuments(),
            'Clinical decision-making in surgery' => getHealthcareMedicalDocuments(),
            'Crisis management & problem-solving' => getHealthcareMedicalDocuments(),
            'Communication with surgical team' => getHealthcareMedicalDocuments(),
            'Surgical procedures & techniques' => getHealthcareMedicalDocuments(),
            'Patient assessment & pre/post-op care' => getHealthcareMedicalDocuments(),
            'Teamwork & communication' => getHealthcareMedicalDocuments(),
            'Clinical support & patient care' => getHealthcareMedicalDocuments(),
            'Vital signs & basic procedures' => getHealthcareMedicalDocuments(),
            'Administrative tasks (scheduling, records)' => getHealthcareMedicalDocuments(),
            'Communication & patient interaction' => getHealthcareMedicalDocuments(),
            'Compliance & safety' => getHealthcareMedicalDocuments(),
            'Medical records management & coding' => getHealthcareMedicalDocuments(),
            'HIPAA / patient privacy compliance' => getHealthcareMedicalDocuments(),
            'Data entry & database management' => getHealthcareMedicalDocuments(),
            'Attention to detail & accuracy' => getHealthcareMedicalDocuments(),
            'Speech & language assessment' => getHealthcareMedicalDocuments(),
            'Therapy planning & intervention' => getHealthcareMedicalDocuments(),
            'Patient instruction & progress tracking' => getHealthcareMedicalDocuments(),
            'Patient assessment & diagnosis' => getHealthcareMedicalDocuments(),
            'Counseling & therapy techniques' => getHealthcareMedicalDocuments(),
            'Research & data analysis' => getHealthcareMedicalDocuments(),
            'Ethical & confidential practice' => getHealthcareMedicalDocuments(),
            'Patient care planning & coordination' => getHealthcareMedicalDocuments(),
            'Communication with healthcare teams' => getHealthcareMedicalDocuments(),
            'Scheduling & administrative support' => getHealthcareMedicalDocuments(),
            'Problem-solving & case management' => getHealthcareMedicalDocuments(),
            
            // Human Resources (HR) Skills - All require full document set
            'Strategic HR planning & leadership' => getHRDocuments(),
            'Employee relations & conflict resolution' => getHRDocuments(),
            'Recruitment & retention strategies' => getHRDocuments(),
            'Performance management & appraisals' => getHRDocuments(),
            'Policy development & compliance' => getHRDocuments(),
            'Communication & decision-making' => getHRDocuments(),
            'Talent sourcing & acquisition' => getHRDocuments(),
            'Interviewing & candidate evaluation' => getHRDocuments(),
            'ATS (Applicant Tracking System) proficiency' => getHRDocuments(),
            'Employer branding & networking' => getHRDocuments(),
            'Communication & negotiation' => getHRDocuments(),
            'Interview scheduling & candidate follow-ups' => getHRDocuments(),
            'Recruitment administration & documentation' => getHRDocuments(),
            'ATS / HRIS support' => getHRDocuments(),
            'Coordination with hiring managers' => getHRDocuments(),
            'Communication & organizational skills' => getHRDocuments(),
            'HR operations & administration' => getHRDocuments(),
            'Recruitment & onboarding support' => getHRDocuments(),
            'Employee relations & performance tracking' => getHRDocuments(),
            'Policy implementation & compliance' => getHRDocuments(),
            'Payroll & benefits administration' => getHRDocuments(),
            'Training needs assessment' => getHRDocuments(),
            'Program planning & scheduling' => getHRDocuments(),
            'Workshop & session facilitation' => getHRDocuments(),
            'Learning management systems (LMS)' => getHRDocuments(),
            'Communication & presentation skills' => getHRDocuments(),
            'Training program design & delivery' => getHRDocuments(),
            'Skill gap analysis' => getHRDocuments(),
            'Coaching & mentoring' => getHRDocuments(),
            'E-learning / LMS platforms' => getHRDocuments(),
            'Evaluation & feedback' => getHRDocuments(),
            'Recruitment strategy & candidate sourcing' => getHRDocuments(),
            'Interviewing & evaluation' => getHRDocuments(),
            'Employer branding & networking' => getHRDocuments(),
            'Recruitment metrics & reporting' => getHRDocuments(),
            'ATS / HRIS system proficiency' => getHRDocuments(),
            'Salary & benefits administration' => getHRDocuments(),
            'Payroll coordination & deductions' => getHRDocuments(),
            'Compensation benchmarking & analysis' => getHRDocuments(),
            'Regulatory compliance (labor laws, taxation)' => getHRDocuments(),
            'Communication & problem-solving' => getHRDocuments(),
            'Administrative support & record keeping' => getHRDocuments(),
            'Employee onboarding assistance' => getHRDocuments(),
            'HRIS / payroll software usage' => getHRDocuments(),
            'Communication & organization' => getHRDocuments(),
            'Scheduling & coordination' => getHRDocuments(),
            'HR record keeping & data management' => getHRDocuments(),
            'Payroll & benefits support' => getHRDocuments(),
            'HR policy adherence' => getHRDocuments(),
            'Administrative coordination' => getHRDocuments(),
            'Conflict resolution & mediation' => getHRDocuments(),
            'Employee engagement & retention' => getHRDocuments(),
            'HR policy implementation' => getHRDocuments(),
            'Communication & interpersonal skills' => getHRDocuments(),
            'Investigation & compliance' => getHRDocuments(),
            'Strategic HR consulting & alignment with business goals' => getHRDocuments(),
            'Workforce planning & change management' => getHRDocuments(),
            'Performance management support' => getHRDocuments(),
            'Stakeholder communication & advisory' => getHRDocuments(),
            'Problem-solving & decision-making' => getHRDocuments(),
            'Recruitment & onboarding coordination' => getHRDocuments(),
            'HR administrative tasks & documentation' => getHRDocuments(),
            'Scheduling & communication' => getHRDocuments(),
            'HRIS / payroll support' => getHRDocuments(),
            'Organizational skills' => getHRDocuments(),
            'Payroll processing & tax compliance' => getHRDocuments(),
            'Salary computation & deductions' => getHRDocuments(),
            'Benefits administration' => getHRDocuments(),
            'HRIS / payroll software proficiency' => getHRDocuments(),
            'Attention to detail & accuracy' => getHRDocuments(),
            'HR data collection & analysis' => getHRDocuments(),
            'Metrics reporting & dashboards' => getHRDocuments(),
            'Process improvement recommendations' => getHRDocuments(),
            'HRIS / Excel & analytics tools' => getHRDocuments(),
            'Problem-solving & communication' => getHRDocuments(),
            'HR strategy & advisory' => getHRDocuments(),
            'Talent management & workforce planning' => getHRDocuments(),
            'Communication & presentation' => getHRDocuments(),
            'Analytical & problem-solving skills' => getHRDocuments(),
            'New hire orientation & integration' => getHRDocuments(),
            'Documentation & compliance' => getHRDocuments(),
            'HRIS / onboarding platforms' => getHRDocuments(),
            'Communication & engagement' => getHRDocuments(),
            'Process coordination' => getHRDocuments(),
            
            // Manufacturing / Production Skills - All require full document set
            'Team supervision & leadership' => getManufacturingProductionDocuments(),
            'Production planning & scheduling' => getManufacturingProductionDocuments(),
            'Quality control & compliance' => getManufacturingProductionDocuments(),
            'Problem-solving & decision-making' => getManufacturingProductionDocuments(),
            'Communication & coordination' => getManufacturingProductionDocuments(),
            'Operating machinery & equipment' => getManufacturingProductionDocuments(),
            'Safety & compliance with protocols' => getManufacturingProductionDocuments(),
            'Basic troubleshooting & maintenance' => getManufacturingProductionDocuments(),
            'Monitoring production output' => getManufacturingProductionDocuments(),
            'Attention to detail' => getManufacturingProductionDocuments(),
            'Product inspection & testing' => getManufacturingProductionDocuments(),
            'Knowledge of quality standards (ISO, Six Sigma)' => getManufacturingProductionDocuments(),
            'Measurement & testing tools proficiency' => getManufacturingProductionDocuments(),
            'Documentation & reporting' => getManufacturingProductionDocuments(),
            'Analytical & problem-solving skills' => getManufacturingProductionDocuments(),
            'Plant operations management' => getManufacturingProductionDocuments(),
            'Production planning & efficiency optimization' => getManufacturingProductionDocuments(),
            'Team leadership & supervision' => getManufacturingProductionDocuments(),
            'Budgeting & resource allocation' => getManufacturingProductionDocuments(),
            'Safety & regulatory compliance' => getManufacturingProductionDocuments(),
            'Production scheduling & workflow optimization' => getManufacturingProductionDocuments(),
            'Resource allocation & inventory coordination' => getManufacturingProductionDocuments(),
            'Data analysis & reporting' => getManufacturingProductionDocuments(),
            'Communication & coordination with teams' => getManufacturingProductionDocuments(),
            'Problem-solving & planning' => getManufacturingProductionDocuments(),
            'Component assembly & fitting' => getManufacturingProductionDocuments(),
            'Reading technical drawings & instructions' => getManufacturingProductionDocuments(),
            'Hand tools & machinery operation' => getManufacturingProductionDocuments(),
            'Quality checks & compliance' => getManufacturingProductionDocuments(),
            'Attention to detail & manual dexterity' => getManufacturingProductionDocuments(),
            'Assembly line operations' => getManufacturingProductionDocuments(),
            'Equipment & tool handling' => getManufacturingProductionDocuments(),
            'Following production protocols & safety standards' => getManufacturingProductionDocuments(),
            'Teamwork & collaboration' => getManufacturingProductionDocuments(),
            'Basic quality checks' => getManufacturingProductionDocuments(),
            'Process optimization & workflow design' => getManufacturingProductionDocuments(),
            'Equipment & machinery specification' => getManufacturingProductionDocuments(),
            'Lean manufacturing & efficiency improvement' => getManufacturingProductionDocuments(),
            'Technical problem-solving' => getManufacturingProductionDocuments(),
            'Supervising assembly/production line' => getManufacturingProductionDocuments(),
            'Scheduling & task delegation' => getManufacturingProductionDocuments(),
            'Quality monitoring & control' => getManufacturingProductionDocuments(),
            'Team leadership & communication' => getManufacturingProductionDocuments(),
            'Problem-solving & reporting' => getManufacturingProductionDocuments(),
            'Shift operations management' => getManufacturingProductionDocuments(),
            'Team supervision & coordination' => getManufacturingProductionDocuments(),
            'Production tracking & reporting' => getManufacturingProductionDocuments(),
            'Quality & safety compliance' => getManufacturingProductionDocuments(),
            'Inventory tracking & management' => getManufacturingProductionDocuments(),
            'Stock reconciliation & reporting' => getManufacturingProductionDocuments(),
            'ERP / inventory software proficiency' => getManufacturingProductionDocuments(),
            'Analytical & organizational skills' => getManufacturingProductionDocuments(),
            'Coordination with production & procurement' => getManufacturingProductionDocuments(),
            'Operating production processes & equipment' => getManufacturingProductionDocuments(),
            'Monitoring process parameters' => getManufacturingProductionDocuments(),
            'Troubleshooting & preventive maintenance' => getManufacturingProductionDocuments(),
            'Compliance with safety & quality standards' => getManufacturingProductionDocuments(),
            'Equipment setup & operation' => getManufacturingProductionDocuments(),
            'Troubleshooting & maintenance' => getManufacturingProductionDocuments(),
            'Process monitoring & optimization' => getManufacturingProductionDocuments(),
            'Quality assurance & documentation' => getManufacturingProductionDocuments(),
            'Communication & teamwork' => getManufacturingProductionDocuments(),
            'Packaging machinery operation' => getManufacturingProductionDocuments(),
            'Quality inspection of packaged products' => getManufacturingProductionDocuments(),
            'Compliance with safety & hygiene standards' => getManufacturingProductionDocuments(),
            'Manual dexterity & attention to detail' => getManufacturingProductionDocuments(),
            'Teamwork & productivity' => getManufacturingProductionDocuments(),
            'Production planning & scheduling' => getManufacturingProductionDocuments(),
            'Resource allocation & workflow optimization' => getManufacturingProductionDocuments(),
            'Communication & coordination with departments' => getManufacturingProductionDocuments(),
            'Problem-solving & time management' => getManufacturingProductionDocuments(),
            'Supervising overall production operations' => getManufacturingProductionDocuments(),
            'Team leadership & task delegation' => getManufacturingProductionDocuments(),
            'Problem-solving & process improvement' => getManufacturingProductionDocuments(),
            'Reporting & coordination' => getManufacturingProductionDocuments(),
            'Equipment installation & maintenance' => getManufacturingProductionDocuments(),
            'Troubleshooting & repair' => getManufacturingProductionDocuments(),
            'Monitoring machinery & process performance' => getManufacturingProductionDocuments(),
            'Safety compliance & documentation' => getManufacturingProductionDocuments(),
            'Communication & technical reporting' => getManufacturingProductionDocuments(),
            
            // Logistics / Warehouse / Supply Chain Skills - All require full document set
            'Warehouse operations & workflow management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory tracking & control' => getLogisticsWarehouseSupplyChainDocuments(),
            'Safety & compliance management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Communication & coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'Shipment scheduling & tracking' => getLogisticsWarehouseSupplyChainDocuments(),
            'Supply chain coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'Transportation & route planning' => getLogisticsWarehouseSupplyChainDocuments(),
            'Communication with vendors & internal teams' => getLogisticsWarehouseSupplyChainDocuments(),
            'Problem-solving & organization' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory monitoring & stock counting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Record keeping & reporting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Coordination with warehouse & procurement teams' => getLogisticsWarehouseSupplyChainDocuments(),
            'Stock monitoring & reconciliation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory audits & reporting' => getLogisticsWarehouseSupplyChainDocuments(),
            'ERP / inventory management systems' => getLogisticsWarehouseSupplyChainDocuments(),
            'Attention to detail & organization' => getLogisticsWarehouseSupplyChainDocuments(),
            'Coordination with warehouse & procurement' => getLogisticsWarehouseSupplyChainDocuments(),
            'Data analysis & reporting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Supply chain optimization & forecasting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Process improvement & efficiency tracking' => getLogisticsWarehouseSupplyChainDocuments(),
            'ERP / analytics tools proficiency' => getLogisticsWarehouseSupplyChainDocuments(),
            'Problem-solving & critical thinking' => getLogisticsWarehouseSupplyChainDocuments(),
            'Shipment processing & documentation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Goods inspection & quality check' => getLogisticsWarehouseSupplyChainDocuments(),
            'Coordination with carriers & warehouse teams' => getLogisticsWarehouseSupplyChainDocuments(),
            'Route planning & optimization' => getLogisticsWarehouseSupplyChainDocuments(),
            'Vehicle scheduling & coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'Communication with drivers & logistics teams' => getLogisticsWarehouseSupplyChainDocuments(),
            'Problem-solving & time management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Vendor sourcing & negotiation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Purchase order management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Cost analysis & budgeting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Compliance with procurement policies' => getLogisticsWarehouseSupplyChainDocuments(),
            'Communication & negotiation skills' => getLogisticsWarehouseSupplyChainDocuments(),
            'Procurement & supply management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory control & replenishment' => getLogisticsWarehouseSupplyChainDocuments(),
            'Vendor coordination & negotiation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Compliance & documentation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Analytical & organizational skills' => getLogisticsWarehouseSupplyChainDocuments(),
            'Vehicle & fleet maintenance' => getLogisticsWarehouseSupplyChainDocuments(),
            'Route planning & logistics coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'Compliance with safety regulations' => getLogisticsWarehouseSupplyChainDocuments(),
            'Budgeting & resource management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Team management & communication' => getLogisticsWarehouseSupplyChainDocuments(),
            'Distribution operations management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory & warehouse coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'Logistics & transportation planning' => getLogisticsWarehouseSupplyChainDocuments(),
            'Team leadership & supervision' => getLogisticsWarehouseSupplyChainDocuments(),
            'Performance tracking & problem-solving' => getLogisticsWarehouseSupplyChainDocuments(),
            'Processing customer orders accurately' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory tracking & picking/packing coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'ERP / order management software proficiency' => getLogisticsWarehouseSupplyChainDocuments(),
            'Communication & coordination with warehouse teams' => getLogisticsWarehouseSupplyChainDocuments(),
            'Attention to detail & timeliness' => getLogisticsWarehouseSupplyChainDocuments(),
            'Picking, packing, & stocking' => getLogisticsWarehouseSupplyChainDocuments(),
            'Equipment handling (forklifts, pallet jacks)' => getLogisticsWarehouseSupplyChainDocuments(),
            'Inventory monitoring & reporting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Safety & compliance adherence' => getLogisticsWarehouseSupplyChainDocuments(),
            'Teamwork & productivity' => getLogisticsWarehouseSupplyChainDocuments(),
            'Shipment coordination & tracking' => getLogisticsWarehouseSupplyChainDocuments(),
            'Vendor & carrier communication' => getLogisticsWarehouseSupplyChainDocuments(),
            'Documentation & record keeping' => getLogisticsWarehouseSupplyChainDocuments(),
            'Problem-solving & workflow optimization' => getLogisticsWarehouseSupplyChainDocuments(),
            'ERP / logistics software knowledge' => getLogisticsWarehouseSupplyChainDocuments(),
            'Overall logistics & supply chain management' => getLogisticsWarehouseSupplyChainDocuments(),
            'Team leadership & coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            'Performance tracking & process improvement' => getLogisticsWarehouseSupplyChainDocuments(),
            'Budgeting & resource allocation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Vendor management & negotiation' => getLogisticsWarehouseSupplyChainDocuments(),
            'Delivery scheduling & route planning' => getLogisticsWarehouseSupplyChainDocuments(),
            'Communication with drivers & customers' => getLogisticsWarehouseSupplyChainDocuments(),
            'Shipment tracking & reporting' => getLogisticsWarehouseSupplyChainDocuments(),
            'Customer service & coordination' => getLogisticsWarehouseSupplyChainDocuments(),
            
            // Marketing / Sales Skills - All require full document set
            'Market research & analysis' => getMarketingSalesDocuments(),
            'Campaign planning & execution' => getMarketingSalesDocuments(),
            'Content creation & copywriting' => getMarketingSalesDocuments(),
            'Communication & presentation' => getMarketingSalesDocuments(),
            'Digital marketing tools (SEO, Google Ads, social media)' => getMarketingSalesDocuments(),
            'Prospecting & lead generation' => getMarketingSalesDocuments(),
            'Negotiation & closing skills' => getMarketingSalesDocuments(),
            'Customer relationship management (CRM tools)' => getMarketingSalesDocuments(),
            'Product knowledge & presentation' => getMarketingSalesDocuments(),
            'Communication & persuasion' => getMarketingSalesDocuments(),
            'Brand strategy & positioning' => getMarketingSalesDocuments(),
            'Marketing campaign planning' => getMarketingSalesDocuments(),
            'Market research & consumer insights' => getMarketingSalesDocuments(),
            'Communication & leadership' => getMarketingSalesDocuments(),
            'Project management & budgeting' => getMarketingSalesDocuments(),
            'Client relationship management' => getMarketingSalesDocuments(),
            'Sales & upselling strategies' => getMarketingSalesDocuments(),
            'Account planning & reporting' => getMarketingSalesDocuments(),
            'Communication & negotiation' => getMarketingSalesDocuments(),
            'Problem-solving & customer service' => getMarketingSalesDocuments(),
            'Strategic account management' => getMarketingSalesDocuments(),
            'Relationship building with high-value clients' => getMarketingSalesDocuments(),
            'Sales planning & performance monitoring' => getMarketingSalesDocuments(),
            'Negotiation & problem-solving' => getMarketingSalesDocuments(),
            'Communication & collaboration' => getMarketingSalesDocuments(),
            'Social media strategy & content creation' => getMarketingSalesDocuments(),
            'Platform management (Facebook, Instagram, LinkedIn, TikTok)' => getMarketingSalesDocuments(),
            'Analytics & performance tracking' => getMarketingSalesDocuments(),
            'Community engagement & communication' => getMarketingSalesDocuments(),
            'Creativity & trend awareness' => getMarketingSalesDocuments(),
            'Campaign coordination & scheduling' => getMarketingSalesDocuments(),
            'Content management & copywriting' => getMarketingSalesDocuments(),
            'Market research & reporting' => getMarketingSalesDocuments(),
            'Communication & teamwork' => getMarketingSalesDocuments(),
            'Digital marketing tools & social media knowledge' => getMarketingSalesDocuments(),
            'Event planning & execution' => getMarketingSalesDocuments(),
            'Vendor coordination & logistics' => getMarketingSalesDocuments(),
            'Budget management & scheduling' => getMarketingSalesDocuments(),
            'Promotion & marketing support' => getMarketingSalesDocuments(),
            'Communication & multitasking' => getMarketingSalesDocuments(),
            'Lead generation & opportunity identification' => getMarketingSalesDocuments(),
            'Market research & competitive analysis' => getMarketingSalesDocuments(),
            'Proposal & presentation skills' => getMarketingSalesDocuments(),
            'Negotiation & relationship-building' => getMarketingSalesDocuments(),
            'Strategic thinking & planning' => getMarketingSalesDocuments(),
            'Media planning & buying' => getMarketingSalesDocuments(),
            'Copywriting & creative development' => getMarketingSalesDocuments(),
            'Campaign & promotional activity planning' => getMarketingSalesDocuments(),
            'Coordination with marketing & sales teams' => getMarketingSalesDocuments(),
            'Event execution & public engagement' => getMarketingSalesDocuments(),
            'Communication & creativity' => getMarketingSalesDocuments(),
            'Performance tracking & reporting' => getMarketingSalesDocuments(),
            'SEO & SEM optimization' => getMarketingSalesDocuments(),
            'Web & social media analytics' => getMarketingSalesDocuments(),
            'Data interpretation & reporting' => getMarketingSalesDocuments(),
            'Campaign performance tracking' => getMarketingSalesDocuments(),
            'Technical marketing tools (Google Analytics, Ads, CRM)' => getMarketingSalesDocuments(),
            'Product lifecycle management' => getMarketingSalesDocuments(),
            'Market research & competitor analysis' => getMarketingSalesDocuments(),
            'Stakeholder communication & coordination' => getMarketingSalesDocuments(),
            'Strategic planning & decision-making' => getMarketingSalesDocuments(),
            'Project management & roadmap creation' => getMarketingSalesDocuments(),
            'Sales team leadership & coaching' => getMarketingSalesDocuments(),
            'Goal setting & target achievement' => getMarketingSalesDocuments(),
            'Customer relationship management' => getMarketingSalesDocuments(),
            'Problem-solving & decision-making' => getMarketingSalesDocuments(),
            'Regional sales planning & execution' => getMarketingSalesDocuments(),
            'Client relationship & territory management' => getMarketingSalesDocuments(),
            'Market analysis & competitor tracking' => getMarketingSalesDocuments(),
            'Team coordination & target achievement' => getMarketingSalesDocuments(),
            'Market research & data analysis' => getMarketingSalesDocuments(),
            'Consumer insights & reporting' => getMarketingSalesDocuments(),
            'Campaign performance evaluation' => getMarketingSalesDocuments(),
            'Presentation & communication' => getMarketingSalesDocuments(),
            'Analytical tools (Excel, SPSS, Google Analytics)' => getMarketingSalesDocuments(),
            
            // Creative / Media / Design Skills - All require full document set
            'Adobe Creative Suite (Photoshop, Illustrator, InDesign)' => getCreativeMediaDesignDocuments(),
            'Typography & layout design' => getCreativeMediaDesignDocuments(),
            'Branding & visual communication' => getCreativeMediaDesignDocuments(),
            'Creativity & conceptual thinking' => getCreativeMediaDesignDocuments(),
            'Attention to detail & time management' => getCreativeMediaDesignDocuments(),
            'Video editing software (Premiere Pro, Final Cut Pro, After Effects)' => getCreativeMediaDesignDocuments(),
            'Storyboarding & sequencing' => getCreativeMediaDesignDocuments(),
            'Color grading & audio editing' => getCreativeMediaDesignDocuments(),
            'Creativity & attention to detail' => getCreativeMediaDesignDocuments(),
            'Time management & collaboration' => getCreativeMediaDesignDocuments(),
            'Social media content development' => getCreativeMediaDesignDocuments(),
            'Photography & videography basics' => getCreativeMediaDesignDocuments(),
            'Copywriting & storytelling' => getCreativeMediaDesignDocuments(),
            'Creativity & audience engagement' => getCreativeMediaDesignDocuments(),
            'Video & graphic editing tools' => getCreativeMediaDesignDocuments(),
            'Visual concept development & design direction' => getCreativeMediaDesignDocuments(),
            'Leadership & team management' => getCreativeMediaDesignDocuments(),
            'Branding & creative strategy' => getCreativeMediaDesignDocuments(),
            'Project management & collaboration' => getCreativeMediaDesignDocuments(),
            'Creativity & critical thinking' => getCreativeMediaDesignDocuments(),
            'Drawing & illustration skills (digital & traditional)' => getCreativeMediaDesignDocuments(),
            'Adobe Illustrator & Procreate' => getCreativeMediaDesignDocuments(),
            'Visual storytelling & conceptual design' => getCreativeMediaDesignDocuments(),
            'Camera operation & composition' => getCreativeMediaDesignDocuments(),
            'Lighting & studio techniques' => getCreativeMediaDesignDocuments(),
            'Photo editing software (Lightroom, Photoshop)' => getCreativeMediaDesignDocuments(),
            'Creativity & visual storytelling' => getCreativeMediaDesignDocuments(),
            'Attention to detail & project management' => getCreativeMediaDesignDocuments(),
            '2D/3D animation software (After Effects, Maya, Blender)' => getCreativeMediaDesignDocuments(),
            'Storyboarding & motion design' => getCreativeMediaDesignDocuments(),
            'Creativity & artistic skills' => getCreativeMediaDesignDocuments(),
            'Timing & pacing' => getCreativeMediaDesignDocuments(),
            'Collaboration & feedback incorporation' => getCreativeMediaDesignDocuments(),
            'Motion graphics & animation software (After Effects, Cinema 4D)' => getCreativeMediaDesignDocuments(),
            'Visual storytelling & conceptualization' => getCreativeMediaDesignDocuments(),
            'Video editing & compositing' => getCreativeMediaDesignDocuments(),
            'Creativity & timing' => getCreativeMediaDesignDocuments(),
            'Collaboration & attention to detail' => getCreativeMediaDesignDocuments(),
            'Writing & storytelling' => getCreativeMediaDesignDocuments(),
            'Marketing & branding knowledge' => getCreativeMediaDesignDocuments(),
            'Editing & proofreading' => getCreativeMediaDesignDocuments(),
            'Creativity & communication' => getCreativeMediaDesignDocuments(),
            'SEO & digital content understanding' => getCreativeMediaDesignDocuments(),
            'Wireframing & prototyping tools (Figma, Sketch, Adobe XD)' => getCreativeMediaDesignDocuments(),
            'User experience & interface design' => getCreativeMediaDesignDocuments(),
            'Research & user testing' => getCreativeMediaDesignDocuments(),
            'Visual design & interaction principles' => getCreativeMediaDesignDocuments(),
            'Communication & problem-solving' => getCreativeMediaDesignDocuments(),
            'Creative strategy & brand vision' => getCreativeMediaDesignDocuments(),
            'Visual communication & conceptual thinking' => getCreativeMediaDesignDocuments(),
            'Creativity & decision-making' => getCreativeMediaDesignDocuments(),
            'Graphic design & visual communication' => getCreativeMediaDesignDocuments(),
            'Branding & typography' => getCreativeMediaDesignDocuments(),
            'Adobe Creative Suite proficiency' => getCreativeMediaDesignDocuments(),
            'Attention to detail & creativity' => getCreativeMediaDesignDocuments(),
            'Collaboration & presentation skills' => getCreativeMediaDesignDocuments(),
            'Web design principles & responsive design' => getCreativeMediaDesignDocuments(),
            'HTML/CSS basics' => getCreativeMediaDesignDocuments(),
            'UI/UX design knowledge' => getCreativeMediaDesignDocuments(),
            'Adobe XD, Figma, or Sketch proficiency' => getCreativeMediaDesignDocuments(),
            'Creativity & problem-solving' => getCreativeMediaDesignDocuments(),
            'Set & production design (film, TV, theatre)' => getCreativeMediaDesignDocuments(),
            'Visual storytelling & concept development' => getCreativeMediaDesignDocuments(),
            'Drafting & layout skills' => getCreativeMediaDesignDocuments(),
            'Collaboration & project coordination' => getCreativeMediaDesignDocuments(),
            'Page layout & composition' => getCreativeMediaDesignDocuments(),
            'Typography & color theory' => getCreativeMediaDesignDocuments(),
            'Adobe InDesign / Illustrator proficiency' => getCreativeMediaDesignDocuments(),
            'Communication & meeting deadlines' => getCreativeMediaDesignDocuments(),
            
            // Construction / Infrastructure Skills - All require full document set
            'Project planning & scheduling' => getConstructionInfrastructureDocuments(),
            'Budgeting & resource allocation' => getConstructionInfrastructureDocuments(),
            'Team leadership & supervision' => getConstructionInfrastructureDocuments(),
            'Health, safety & compliance management' => getConstructionInfrastructureDocuments(),
            'Communication & problem-solving' => getConstructionInfrastructureDocuments(),
            'Site planning & layout' => getConstructionInfrastructureDocuments(),
            'Construction supervision & inspection' => getConstructionInfrastructureDocuments(),
            'Technical drawings & specifications interpretation' => getConstructionInfrastructureDocuments(),
            'Quality control & compliance' => getConstructionInfrastructureDocuments(),
            'Problem-solving & teamwork' => getConstructionInfrastructureDocuments(),
            'Design & conceptualization' => getConstructionInfrastructureDocuments(),
            'CAD / Revit / SketchUp proficiency' => getConstructionInfrastructureDocuments(),
            'Technical drawings & documentation' => getConstructionInfrastructureDocuments(),
            'Creativity & spatial awareness' => getConstructionInfrastructureDocuments(),
            'Project coordination & communication' => getConstructionInfrastructureDocuments(),
            'Supervising construction crews' => getConstructionInfrastructureDocuments(),
            'Task delegation & scheduling' => getConstructionInfrastructureDocuments(),
            'Safety & compliance enforcement' => getConstructionInfrastructureDocuments(),
            'Quality control & site monitoring' => getConstructionInfrastructureDocuments(),
            'Communication & leadership' => getConstructionInfrastructureDocuments(),
            'Project planning & execution' => getConstructionInfrastructureDocuments(),
            'Budget & resource management' => getConstructionInfrastructureDocuments(),
            'Risk assessment & mitigation' => getConstructionInfrastructureDocuments(),
            'Team leadership & coordination' => getConstructionInfrastructureDocuments(),
            'Communication & stakeholder management' => getConstructionInfrastructureDocuments(),
            'Cost estimation & budgeting' => getConstructionInfrastructureDocuments(),
            'Material take-offs & procurement' => getConstructionInfrastructureDocuments(),
            'Contract management & compliance' => getConstructionInfrastructureDocuments(),
            'Risk analysis & cost control' => getConstructionInfrastructureDocuments(),
            'Analytical & communication skills' => getConstructionInfrastructureDocuments(),
            'Drafting & technical drawing interpretation' => getConstructionInfrastructureDocuments(),
            'Site surveying & inspection' => getConstructionInfrastructureDocuments(),
            'Construction support & reporting' => getConstructionInfrastructureDocuments(),
            'Knowledge of materials & construction methods' => getConstructionInfrastructureDocuments(),
            'Teamwork & problem-solving' => getConstructionInfrastructureDocuments(),
            'Structural analysis & design' => getConstructionInfrastructureDocuments(),
            'CAD / structural design software' => getConstructionInfrastructureDocuments(),
            'Material & load calculations' => getConstructionInfrastructureDocuments(),
            'Compliance with building codes & safety standards' => getConstructionInfrastructureDocuments(),
            'Problem-solving & attention to detail' => getConstructionInfrastructureDocuments(),
            'Site safety management & inspections' => getConstructionInfrastructureDocuments(),
            'Risk assessment & hazard identification' => getConstructionInfrastructureDocuments(),
            'Compliance with OSHA / local safety regulations' => getConstructionInfrastructureDocuments(),
            'Emergency response planning' => getConstructionInfrastructureDocuments(),
            'Communication & training' => getConstructionInfrastructureDocuments(),
            'Site inspection & quality assessment' => getConstructionInfrastructureDocuments(),
            'Compliance with building codes & regulations' => getConstructionInfrastructureDocuments(),
            'Documentation & reporting' => getConstructionInfrastructureDocuments(),
            'Attention to detail & problem-solving' => getConstructionInfrastructureDocuments(),
            'Communication & coordination' => getConstructionInfrastructureDocuments(),
            'Supervising daily construction operations' => getConstructionInfrastructureDocuments(),
            'Team coordination & scheduling' => getConstructionInfrastructureDocuments(),
            'Quality assurance & safety compliance' => getConstructionInfrastructureDocuments(),
            'Reporting & problem-solving' => getConstructionInfrastructureDocuments(),
            'On-site engineering support' => getConstructionInfrastructureDocuments(),
            'Construction monitoring & reporting' => getConstructionInfrastructureDocuments(),
            'Technical problem-solving' => getConstructionInfrastructureDocuments(),
            'Coordination with project teams' => getConstructionInfrastructureDocuments(),
            'Safety compliance & communication' => getConstructionInfrastructureDocuments(),
            'Project planning & technical oversight' => getConstructionInfrastructureDocuments(),
            'Cost control & scheduling' => getConstructionInfrastructureDocuments(),
            'Design & construction coordination' => getConstructionInfrastructureDocuments(),
            'Problem-solving & reporting' => getConstructionInfrastructureDocuments(),
            'Site team supervision & scheduling' => getConstructionInfrastructureDocuments(),
            'Quality & safety compliance' => getConstructionInfrastructureDocuments(),
            'Daily operations monitoring' => getConstructionInfrastructureDocuments(),
            'Coordination with engineers & foremen' => getConstructionInfrastructureDocuments(),
            'Problem-solving & leadership' => getConstructionInfrastructureDocuments(),
            'Cost estimation & budgeting' => getConstructionInfrastructureDocuments(),
            'Material quantity calculation' => getConstructionInfrastructureDocuments(),
            'Tendering & proposal preparation' => getConstructionInfrastructureDocuments(),
            'Analytical & numerical skills' => getConstructionInfrastructureDocuments(),
            'Communication & reporting' => getConstructionInfrastructureDocuments(),
            
            // Food / Hospitality / Tourism Skills - All require full document set
            'Menu planning & recipe development' => getFoodHospitalityTourismDocuments(),
            'Food preparation & cooking techniques' => getFoodHospitalityTourismDocuments(),
            'Kitchen management & leadership' => getFoodHospitalityTourismDocuments(),
            'Food safety & hygiene compliance' => getFoodHospitalityTourismDocuments(),
            'Creativity & time management' => getFoodHospitalityTourismDocuments(),
            'Assisting head chef in kitchen operations' => getFoodHospitalityTourismDocuments(),
            'Supervising kitchen staff & line cooks' => getFoodHospitalityTourismDocuments(),
            'Food preparation & quality control' => getFoodHospitalityTourismDocuments(),
            'Inventory & stock management' => getFoodHospitalityTourismDocuments(),
            'Communication & problem-solving' => getFoodHospitalityTourismDocuments(),
            'Cooking & food preparation on specific stations' => getFoodHospitalityTourismDocuments(),
            'Following recipes & portion control' => getFoodHospitalityTourismDocuments(),
            'Maintaining kitchen hygiene & safety' => getFoodHospitalityTourismDocuments(),
            'Time management & multitasking' => getFoodHospitalityTourismDocuments(),
            'Teamwork & communication' => getFoodHospitalityTourismDocuments(),
            'Ingredient preparation & mise en place' => getFoodHospitalityTourismDocuments(),
            'Cutting, chopping, and portioning' => getFoodHospitalityTourismDocuments(),
            'Maintaining cleanliness & organization' => getFoodHospitalityTourismDocuments(),
            'Following recipes & instructions' => getFoodHospitalityTourismDocuments(),
            'Speed & accuracy' => getFoodHospitalityTourismDocuments(),
            'Operating grill stations & cooking meat/fish' => getFoodHospitalityTourismDocuments(),
            'Temperature control & timing' => getFoodHospitalityTourismDocuments(),
            'Food quality & presentation' => getFoodHospitalityTourismDocuments(),
            'Kitchen safety & sanitation' => getFoodHospitalityTourismDocuments(),
            'Team coordination & efficiency' => getFoodHospitalityTourismDocuments(),
            'Operating fryers & cooking fried foods' => getFoodHospitalityTourismDocuments(),
            'Food safety & hygiene' => getFoodHospitalityTourismDocuments(),
            'Speed & multitasking' => getFoodHospitalityTourismDocuments(),
            'Preparing breakfast items (eggs, pancakes, etc.)' => getFoodHospitalityTourismDocuments(),
            'Food presentation & quality control' => getFoodHospitalityTourismDocuments(),
            'Hygiene & teamwork' => getFoodHospitalityTourismDocuments(),
            'Baking & dessert preparation' => getFoodHospitalityTourismDocuments(),
            'Recipe following & portion control' => getFoodHospitalityTourismDocuments(),
            'Presentation & creativity' => getFoodHospitalityTourismDocuments(),
            'Kitchen hygiene & safety' => getFoodHospitalityTourismDocuments(),
            'Time management & teamwork' => getFoodHospitalityTourismDocuments(),
            'Bread & pastry production' => getFoodHospitalityTourismDocuments(),
            'Dough preparation & baking techniques' => getFoodHospitalityTourismDocuments(),
            'Oven management & timing' => getFoodHospitalityTourismDocuments(),
            'Quality control & presentation' => getFoodHospitalityTourismDocuments(),
            'Hygiene & attention to detail' => getFoodHospitalityTourismDocuments(),
            'Coffee & beverage preparation' => getFoodHospitalityTourismDocuments(),
            'Machine operation & maintenance' => getFoodHospitalityTourismDocuments(),
            'Customer service & communication' => getFoodHospitalityTourismDocuments(),
            'Cleanliness & hygiene' => getFoodHospitalityTourismDocuments(),
            'Food preparation & assembly' => getFoodHospitalityTourismDocuments(),
            'Customer service & order taking' => getFoodHospitalityTourismDocuments(),
            'Cleanliness & hygiene compliance' => getFoodHospitalityTourismDocuments(),
            'Teamwork & coordination' => getFoodHospitalityTourismDocuments(),
            'Operating kitchen & service equipment' => getFoodHospitalityTourismDocuments(),
            'Staff supervision & scheduling' => getFoodHospitalityTourismDocuments(),
            'Customer service management' => getFoodHospitalityTourismDocuments(),
            'Inventory & supply management' => getFoodHospitalityTourismDocuments(),
            'Financial & operational oversight' => getFoodHospitalityTourismDocuments(),
            'Problem-solving & conflict resolution' => getFoodHospitalityTourismDocuments(),
            'Food preparation & cleaning' => getFoodHospitalityTourismDocuments(),
            'Supporting cooks & chefs' => getFoodHospitalityTourismDocuments(),
            'Equipment handling & maintenance' => getFoodHospitalityTourismDocuments(),
            'Supervising staff during shifts' => getFoodHospitalityTourismDocuments(),
            'Ensuring service quality & safety compliance' => getFoodHospitalityTourismDocuments(),
            'Task delegation & workflow management' => getFoodHospitalityTourismDocuments(),
            'Problem-solving & customer service' => getFoodHospitalityTourismDocuments(),
            'Reporting & documentation' => getFoodHospitalityTourismDocuments(),
            'Handling payments & POS systems' => getFoodHospitalityTourismDocuments(),
            'Accuracy & attention to detail' => getFoodHospitalityTourismDocuments(),
            'Handling cash & financial transactions' => getFoodHospitalityTourismDocuments(),
            'Problem-solving & efficiency' => getFoodHospitalityTourismDocuments(),
            'Greeting & seating customers' => getFoodHospitalityTourismDocuments(),
            'Reservation management' => getFoodHospitalityTourismDocuments(),
            'Multitasking & organizational skills' => getFoodHospitalityTourismDocuments(),
            'Problem-solving & coordination' => getFoodHospitalityTourismDocuments(),
            'Delivering food to tables promptly' => getFoodHospitalityTourismDocuments(),
            'Coordination with kitchen & waitstaff' => getFoodHospitalityTourismDocuments(),
            'Maintaining cleanliness & presentation' => getFoodHospitalityTourismDocuments(),
            'Efficiency & time management' => getFoodHospitalityTourismDocuments(),
            'Taking orders & serving food' => getFoodHospitalityTourismDocuments(),
            'Menu knowledge & upselling' => getFoodHospitalityTourismDocuments(),
            'Multitasking & organization' => getFoodHospitalityTourismDocuments(),
            'Drink preparation & mixology' => getFoodHospitalityTourismDocuments(),
            'Customer interaction & service' => getFoodHospitalityTourismDocuments(),
            'Hygiene & compliance' => getFoodHospitalityTourismDocuments(),
            'Speed, multitasking & creativity' => getFoodHospitalityTourismDocuments(),
            'Check-in & check-out procedures' => getFoodHospitalityTourismDocuments(),
            'Customer service & problem-solving' => getFoodHospitalityTourismDocuments(),
            'Billing & record keeping' => getFoodHospitalityTourismDocuments(),
            'Guest assistance & personalized service' => getFoodHospitalityTourismDocuments(),
            'Booking & travel arrangements' => getFoodHospitalityTourismDocuments(),
            'Local knowledge & recommendations' => getFoodHospitalityTourismDocuments(),
            'Professionalism & multitasking' => getFoodHospitalityTourismDocuments(),
            'Guiding & presenting information to guests' => getFoodHospitalityTourismDocuments(),
            'Communication & public speaking' => getFoodHospitalityTourismDocuments(),
            'Local history & cultural knowledge' => getFoodHospitalityTourismDocuments(),
            'Customer service & engagement' => getFoodHospitalityTourismDocuments(),
            'Time management & planning' => getFoodHospitalityTourismDocuments(),
            'Event planning & organization' => getFoodHospitalityTourismDocuments(),
            'Vendor coordination & scheduling' => getFoodHospitalityTourismDocuments(),
            'Budgeting & logistics management' => getFoodHospitalityTourismDocuments(),
            'Creativity & attention to detail' => getFoodHospitalityTourismDocuments(),
            'Setup & presentation of catering events' => getFoodHospitalityTourismDocuments(),
            'Efficiency & reliability' => getFoodHospitalityTourismDocuments(),
            
            // Retail / Sales Operations Skills - All require full document set
            'Team leadership & staff supervision' => getRetailSalesOperationsDocuments(),
            'Sales target achievement & performance tracking' => getRetailSalesOperationsDocuments(),
            'Inventory & stock management' => getRetailSalesOperationsDocuments(),
            'Customer service & complaint resolution' => getRetailSalesOperationsDocuments(),
            'Budgeting & operational planning' => getRetailSalesOperationsDocuments(),
            'Supporting store operations & management' => getRetailSalesOperationsDocuments(),
            'Staff supervision & training' => getRetailSalesOperationsDocuments(),
            'Inventory & sales monitoring' => getRetailSalesOperationsDocuments(),
            'Customer service & problem-solving' => getRetailSalesOperationsDocuments(),
            'Communication & operational planning' => getRetailSalesOperationsDocuments(),
            'Customer service & communication' => getRetailSalesOperationsDocuments(),
            'Product knowledge & recommendation' => getRetailSalesOperationsDocuments(),
            'Sales & upselling techniques' => getRetailSalesOperationsDocuments(),
            'Cash handling & POS operation' => getRetailSalesOperationsDocuments(),
            'Teamwork & reliability' => getRetailSalesOperationsDocuments(),
            'Prospecting & lead generation' => getRetailSalesOperationsDocuments(),
            'Product knowledge & presentations' => getRetailSalesOperationsDocuments(),
            'Sales target achievement' => getRetailSalesOperationsDocuments(),
            'Customer relationship management' => getRetailSalesOperationsDocuments(),
            'Communication & negotiation' => getRetailSalesOperationsDocuments(),
            'Sales target monitoring & achievement' => getRetailSalesOperationsDocuments(),
            'Customer service & product guidance' => getRetailSalesOperationsDocuments(),
            'Upselling & promotions' => getRetailSalesOperationsDocuments(),
            'Inventory support' => getRetailSalesOperationsDocuments(),
            'Communication & teamwork' => getRetailSalesOperationsDocuments(),
            'Product placement & visual merchandising' => getRetailSalesOperationsDocuments(),
            'Stock rotation & inventory management' => getRetailSalesOperationsDocuments(),
            'Sales analysis & reporting' => getRetailSalesOperationsDocuments(),
            'Attention to detail & creativity' => getRetailSalesOperationsDocuments(),
            'Coordination with store management' => getRetailSalesOperationsDocuments(),
            'Store layout & product display design' => getRetailSalesOperationsDocuments(),
            'Creativity & aesthetic sense' => getRetailSalesOperationsDocuments(),
            'Brand consistency & promotional setup' => getRetailSalesOperationsDocuments(),
            'Inventory coordination' => getRetailSalesOperationsDocuments(),
            'Cash handling & POS system operation' => getRetailSalesOperationsDocuments(),
            'Accuracy & attention to detail' => getRetailSalesOperationsDocuments(),
            'Basic math & record keeping' => getRetailSalesOperationsDocuments(),
            'Efficiency & professionalism' => getRetailSalesOperationsDocuments(),
            'Staff supervision & scheduling' => getRetailSalesOperationsDocuments(),
            'Sales target monitoring' => getRetailSalesOperationsDocuments(),
            'Inventory & operational oversight' => getRetailSalesOperationsDocuments(),
            'Communication & leadership' => getRetailSalesOperationsDocuments(),
            'Supervision of floor staff & operations' => getRetailSalesOperationsDocuments(),
            'Sales performance monitoring' => getRetailSalesOperationsDocuments(),
            'Visual merchandising oversight' => getRetailSalesOperationsDocuments(),
            'Coordination & communication' => getRetailSalesOperationsDocuments(),
            'Receiving, storing, & organizing stock' => getRetailSalesOperationsDocuments(),
            'Inventory tracking & reporting' => getRetailSalesOperationsDocuments(),
            'Stock rotation & quality control' => getRetailSalesOperationsDocuments(),
            'Teamwork & efficiency' => getRetailSalesOperationsDocuments(),
            'Attention to detail' => getRetailSalesOperationsDocuments(),
            'Inventory monitoring & reconciliation' => getRetailSalesOperationsDocuments(),
            'Stock audits & reporting' => getRetailSalesOperationsDocuments(),
            'ERP / inventory system usage' => getRetailSalesOperationsDocuments(),
            'Attention to detail & accuracy' => getRetailSalesOperationsDocuments(),
            'Coordination with store & warehouse teams' => getRetailSalesOperationsDocuments(),
            'Sales order processing & tracking' => getRetailSalesOperationsDocuments(),
            'Customer communication & support' => getRetailSalesOperationsDocuments(),
            'Coordination with sales team & management' => getRetailSalesOperationsDocuments(),
            'Reporting & documentation' => getRetailSalesOperationsDocuments(),
            'Organizational & multitasking skills' => getRetailSalesOperationsDocuments(),
            'Customer support & problem-solving' => getRetailSalesOperationsDocuments(),
            'Communication & interpersonal skills' => getRetailSalesOperationsDocuments(),
            'Product knowledge & guidance' => getRetailSalesOperationsDocuments(),
            'Record keeping & reporting' => getRetailSalesOperationsDocuments(),
            'Patience & professionalism' => getRetailSalesOperationsDocuments(),
            'Managing major client accounts' => getRetailSalesOperationsDocuments(),
            'Sales strategy & target achievement' => getRetailSalesOperationsDocuments(),
            'Relationship building & negotiation' => getRetailSalesOperationsDocuments(),
            'Reporting & coordination' => getRetailSalesOperationsDocuments(),
            'Communication & analytical skills' => getRetailSalesOperationsDocuments(),
            'Customer service & assistance' => getRetailSalesOperationsDocuments(),
            'Stock organization & shelf arrangement' => getRetailSalesOperationsDocuments(),
            'Cleanliness & hygiene maintenance' => getRetailSalesOperationsDocuments(),
            'Product display & visual merchandising' => getRetailSalesOperationsDocuments(),
            'Coordination with merchandising & marketing teams' => getRetailSalesOperationsDocuments(),
            'Store layout & promotional setup' => getRetailSalesOperationsDocuments(),
            
            // Transportation Skills - All require full document set
            'Safe driving & traffic law compliance' => getTransportationDocuments(),
            'Vehicle operation & maintenance' => getTransportationDocuments(),
            'Route planning & navigation' => getTransportationDocuments(),
            'Time management & punctuality' => getTransportationDocuments(),
            'Customer service & communication' => getTransportationDocuments(),
            'Safe motorcycle/bike operation' => getTransportationDocuments(),
            'Route planning & timely delivery' => getTransportationDocuments(),
            'Package handling & documentation' => getTransportationDocuments(),
            'Navigation & problem-solving' => getTransportationDocuments(),
            'Vehicle & fleet management' => getTransportationDocuments(),
            'Maintenance scheduling & oversight' => getTransportationDocuments(),
            'Route planning & logistics coordination' => getTransportationDocuments(),
            'Budgeting & cost control' => getTransportationDocuments(),
            'Team management & communication' => getTransportationDocuments(),
            'Scheduling & dispatching vehicles' => getTransportationDocuments(),
            'Route optimization & tracking' => getTransportationDocuments(),
            'Coordination with drivers & clients' => getTransportationDocuments(),
            'Record keeping & reporting' => getTransportationDocuments(),
            'Problem-solving & multitasking' => getTransportationDocuments(),
            'Safe operation of delivery vehicles' => getTransportationDocuments(),
            'Loading & unloading procedures' => getTransportationDocuments(),
            'Route planning & delivery tracking' => getTransportationDocuments(),
            'Compliance with transportation regulations' => getTransportationDocuments(),
            'Passenger safety & driving compliance' => getTransportationDocuments(),
            'Vehicle inspection & maintenance' => getTransportationDocuments(),
            'Route adherence & time management' => getTransportationDocuments(),
            'Emergency response skills' => getTransportationDocuments(),
            'Navigation & route optimization' => getTransportationDocuments(),
            'Customer service & interpersonal skills' => getTransportationDocuments(),
            'Fare handling & record keeping' => getTransportationDocuments(),
            'Problem-solving & punctuality' => getTransportationDocuments(),
            'Handling & loading cargo safely' => getTransportationDocuments(),
            'Equipment operation (forklifts, pallet jacks)' => getTransportationDocuments(),
            'Documentation & inventory tracking' => getTransportationDocuments(),
            'Compliance with aviation safety regulations' => getTransportationDocuments(),
            'Teamwork & efficiency' => getTransportationDocuments(),
            'Scheduling & coordinating deliveries' => getTransportationDocuments(),
            'Communication with drivers & clients' => getTransportationDocuments(),
            'Monitoring fleet movement & performance' => getTransportationDocuments(),
            'Vehicle inspection & diagnostics' => getTransportationDocuments(),
            'Maintenance scheduling & compliance checks' => getTransportationDocuments(),
            'Safety & regulatory adherence' => getTransportationDocuments(),
            'Attention to detail & reporting' => getTransportationDocuments(),
            'Technical problem-solving' => getTransportationDocuments(),
            'Safe operation of heavy vehicles' => getTransportationDocuments(),
            'Route planning & delivery scheduling' => getTransportationDocuments(),
            'Vehicle maintenance & safety checks' => getTransportationDocuments(),
            'Documentation & compliance' => getTransportationDocuments(),
            'Passenger safety & timely transport' => getTransportationDocuments(),
            'Route adherence & scheduling' => getTransportationDocuments(),
            'Emergency response & problem-solving' => getTransportationDocuments(),
            'Transport planning & coordination' => getTransportationDocuments(),
            'Fleet & vehicle management' => getTransportationDocuments(),
            'Compliance with safety & regulations' => getTransportationDocuments(),
            'Supervising delivery staff & operations' => getTransportationDocuments(),
            'Monitoring delivery performance & compliance' => getTransportationDocuments(),
            'Communication & team coordination' => getTransportationDocuments(),
            
            // Law Enforcement / Criminology Skills - All require full document set
            'Law enforcement & public safety' => getLawEnforcementCriminologyDocuments(),
            'Patrolling & incident response' => getLawEnforcementCriminologyDocuments(),
            'Conflict resolution & communication' => getLawEnforcementCriminologyDocuments(),
            'Report writing & documentation' => getLawEnforcementCriminologyDocuments(),
            'Physical fitness & situational awareness' => getLawEnforcementCriminologyDocuments(),
            'Criminal investigation & evidence collection' => getLawEnforcementCriminologyDocuments(),
            'Interviewing & interrogation techniques' => getLawEnforcementCriminologyDocuments(),
            'Case analysis & report writing' => getLawEnforcementCriminologyDocuments(),
            'Critical thinking & problem-solving' => getLawEnforcementCriminologyDocuments(),
            'Discretion & ethical judgment' => getLawEnforcementCriminologyDocuments(),
            'Evidence collection & preservation' => getLawEnforcementCriminologyDocuments(),
            'Forensic analysis & documentation' => getLawEnforcementCriminologyDocuments(),
            'Photography & scene mapping' => getLawEnforcementCriminologyDocuments(),
            'Knowledge of forensic protocols' => getLawEnforcementCriminologyDocuments(),
            'Attention to detail & analytical skills' => getLawEnforcementCriminologyDocuments(),
            'Threat assessment & risk management' => getLawEnforcementCriminologyDocuments(),
            'Surveillance & monitoring' => getLawEnforcementCriminologyDocuments(),
            'Cybersecurity or physical security knowledge' => getLawEnforcementCriminologyDocuments(),
            'Report writing & incident documentation' => getLawEnforcementCriminologyDocuments(),
            'Analytical & problem-solving skills' => getLawEnforcementCriminologyDocuments(),
            'Laboratory testing & analysis' => getLawEnforcementCriminologyDocuments(),
            'Evidence handling & chain of custody' => getLawEnforcementCriminologyDocuments(),
            'Knowledge of forensic techniques (DNA, fingerprinting, etc.)' => getLawEnforcementCriminologyDocuments(),
            'Report preparation & documentation' => getLawEnforcementCriminologyDocuments(),
            'Attention to detail & analytical thinking' => getLawEnforcementCriminologyDocuments(),
            'Inmate supervision & safety enforcement' => getLawEnforcementCriminologyDocuments(),
            'Conflict resolution & crisis management' => getLawEnforcementCriminologyDocuments(),
            'Documentation & reporting' => getLawEnforcementCriminologyDocuments(),
            'Security protocol adherence' => getLawEnforcementCriminologyDocuments(),
            'Communication & physical fitness' => getLawEnforcementCriminologyDocuments(),
            'Data collection & crime trend analysis' => getLawEnforcementCriminologyDocuments(),
            'Statistical & analytical skills' => getLawEnforcementCriminologyDocuments(),
            'Reporting & visualization' => getLawEnforcementCriminologyDocuments(),
            'Knowledge of law enforcement databases' => getLawEnforcementCriminologyDocuments(),
            'Critical thinking & problem-solving' => getLawEnforcementCriminologyDocuments(),
            'Information gathering & analysis' => getLawEnforcementCriminologyDocuments(),
            'Risk assessment & threat evaluation' => getLawEnforcementCriminologyDocuments(),
            'Report writing & briefing skills' => getLawEnforcementCriminologyDocuments(),
            'Critical thinking & discretion' => getLawEnforcementCriminologyDocuments(),
            'Communication & coordination with agencies' => getLawEnforcementCriminologyDocuments(),
            'Patrolling & law enforcement' => getLawEnforcementCriminologyDocuments(),
            'Emergency response & first aid' => getLawEnforcementCriminologyDocuments(),
            'Conflict management & communication' => getLawEnforcementCriminologyDocuments(),
            'Observation & reporting skills' => getLawEnforcementCriminologyDocuments(),
            'Case investigation & evidence gathering' => getLawEnforcementCriminologyDocuments(),
            'Interviewing witnesses & suspects' => getLawEnforcementCriminologyDocuments(),
            'Analytical & problem-solving skills' => getLawEnforcementCriminologyDocuments(),
            'Department leadership & management' => getLawEnforcementCriminologyDocuments(),
            'Strategic planning & policy implementation' => getLawEnforcementCriminologyDocuments(),
            'Crisis management & decision-making' => getLawEnforcementCriminologyDocuments(),
            'Communication & stakeholder engagement' => getLawEnforcementCriminologyDocuments(),
            'Supervisory & team leadership' => getLawEnforcementCriminologyDocuments(),
            'Leading investigation teams' => getLawEnforcementCriminologyDocuments(),
            'Case management & supervision' => getLawEnforcementCriminologyDocuments(),
            'Mentoring & training junior officers' => getLawEnforcementCriminologyDocuments(),
            'Conflict resolution & decision-making' => getLawEnforcementCriminologyDocuments(),
            'Analytical & investigative skills' => getLawEnforcementCriminologyDocuments(),
            'Community engagement & education' => getLawEnforcementCriminologyDocuments(),
            'Crime prevention program planning' => getLawEnforcementCriminologyDocuments(),
            'Risk assessment & threat mitigation' => getLawEnforcementCriminologyDocuments(),
            'Communication & interpersonal skills' => getLawEnforcementCriminologyDocuments(),
            'Laboratory testing & evidence analysis' => getLawEnforcementCriminologyDocuments(),
            'Knowledge of forensic tools & methods' => getLawEnforcementCriminologyDocuments(),
            'Data interpretation & reporting' => getLawEnforcementCriminologyDocuments(),
            'Compliance with legal & safety protocols' => getLawEnforcementCriminologyDocuments(),
            
            // Security Services
            'Patrolling & surveillance' => getSecurityServicesDocuments(),
            'Access control & monitoring' => getSecurityServicesDocuments(),
            'Emergency response & first aid' => getSecurityServicesDocuments(),
            'Report writing & documentation' => getSecurityServicesDocuments(),
            'Communication & situational awareness' => getSecurityServicesDocuments(),
            'Supervising security personnel' => getSecurityServicesDocuments(),
            'Scheduling & task delegation' => getSecurityServicesDocuments(),
            'Incident response & investigation' => getSecurityServicesDocuments(),
            'Communication & team coordination' => getSecurityServicesDocuments(),
            'Compliance with safety protocols' => getSecurityServicesDocuments(),
            'Theft prevention & monitoring' => getSecurityServicesDocuments(),
            'Risk assessment & vulnerability analysis' => getSecurityServicesDocuments(),
            'Investigating incidents & reporting' => getSecurityServicesDocuments(),
            'Customer service & conflict resolution' => getSecurityServicesDocuments(),
            'Attention to detail & integrity' => getSecurityServicesDocuments(),
            'Personal protection & threat assessment' => getSecurityServicesDocuments(),
            'Close protection techniques & situational awareness' => getSecurityServicesDocuments(),
            'Emergency response & evacuation planning' => getSecurityServicesDocuments(),
            'Physical fitness & defensive tactics' => getSecurityServicesDocuments(),
            'Discretion & communication' => getSecurityServicesDocuments(),
            'Security planning & scheduling' => getSecurityServicesDocuments(),
            'Team coordination & task management' => getSecurityServicesDocuments(),
            'Risk assessment & mitigation' => getSecurityServicesDocuments(),
            'Reporting & documentation' => getSecurityServicesDocuments(),
            'Communication & problem-solving' => getSecurityServicesDocuments(),
            'Monitoring alarm systems & alerts' => getSecurityServicesDocuments(),
            'Responding to security breaches' => getSecurityServicesDocuments(),
            'Equipment operation & troubleshooting' => getSecurityServicesDocuments(),
            'Attention to detail & technical knowledge' => getSecurityServicesDocuments(),
            'Operating CCTV & surveillance systems' => getSecurityServicesDocuments(),
            'Monitoring for suspicious activity' => getSecurityServicesDocuments(),
            'Incident reporting & documentation' => getSecurityServicesDocuments(),
            'Attention to detail & focus' => getSecurityServicesDocuments(),
            'Communication & coordination with security teams' => getSecurityServicesDocuments(),
            'Risk assessment & security planning' => getSecurityServicesDocuments(),
            'Policy development & compliance' => getSecurityServicesDocuments(),
            'Security audits & recommendations' => getSecurityServicesDocuments(),
            'Communication & presentation skills' => getSecurityServicesDocuments(),
            'Analytical & problem-solving skills' => getSecurityServicesDocuments(),
            'Close protection & situational awareness' => getSecurityServicesDocuments(),
            'Risk mitigation & emergency response' => getSecurityServicesDocuments(),
            'Communication & discretion' => getSecurityServicesDocuments(),
            'Crowd management & access control' => getSecurityServicesDocuments(),
            'Monitoring & patrolling event premises' => getSecurityServicesDocuments(),
            'Emergency response & incident reporting' => getSecurityServicesDocuments(),
            'Communication & teamwork' => getSecurityServicesDocuments(),
            'Conflict resolution & situational awareness' => getSecurityServicesDocuments(),
            'Access control & patrolling' => getSecurityServicesDocuments(),
            'Surveillance & monitoring' => getSecurityServicesDocuments(),
            'Emergency response & reporting' => getSecurityServicesDocuments(),
            'Leading security teams & operations' => getSecurityServicesDocuments(),
            'Risk assessment & mitigation planning' => getSecurityServicesDocuments(),
            'Incident investigation & reporting' => getSecurityServicesDocuments(),
            'Communication & team leadership' => getSecurityServicesDocuments(),
            'Workplace safety & security monitoring' => getSecurityServicesDocuments(),
            'Risk assessment & emergency planning' => getSecurityServicesDocuments(),
            'Compliance with safety regulations' => getSecurityServicesDocuments(),
            
            // Skilled / Technical (TESDA)
            'Electrical installation & wiring' => getSkilledTechnicalTesdaDocuments(),
            'Troubleshooting & repair' => getSkilledTechnicalTesdaDocuments(),
            'Knowledge of electrical codes & safety regulations' => getSkilledTechnicalTesdaDocuments(),
            'Reading blueprints & technical diagrams' => getSkilledTechnicalTesdaDocuments(),
            'Problem-solving & attention to detail' => getSkilledTechnicalTesdaDocuments(),
            'Welding techniques (MIG, TIG, Stick, etc.)' => getSkilledTechnicalTesdaDocuments(),
            'Metal fabrication & assembly' => getSkilledTechnicalTesdaDocuments(),
            'Reading technical drawings & blueprints' => getSkilledTechnicalTesdaDocuments(),
            'Safety & protective equipment usage' => getSkilledTechnicalTesdaDocuments(),
            'Precision & attention to detail' => getSkilledTechnicalTesdaDocuments(),
            'Vehicle diagnostics & repair' => getSkilledTechnicalTesdaDocuments(),
            'Engine & electrical systems troubleshooting' => getSkilledTechnicalTesdaDocuments(),
            'Maintenance & service procedures' => getSkilledTechnicalTesdaDocuments(),
            'Knowledge of automotive tools & equipment' => getSkilledTechnicalTesdaDocuments(),
            'Woodworking & furniture construction' => getSkilledTechnicalTesdaDocuments(),
            'Measuring, cutting & assembling materials' => getSkilledTechnicalTesdaDocuments(),
            'Use of hand & power tools' => getSkilledTechnicalTesdaDocuments(),
            'Attention to detail & craftsmanship' => getSkilledTechnicalTesdaDocuments(),
            'Installation & repair of pipes & fixtures' => getSkilledTechnicalTesdaDocuments(),
            'Reading technical diagrams & blueprints' => getSkilledTechnicalTesdaDocuments(),
            'Knowledge of plumbing codes & safety regulations' => getSkilledTechnicalTesdaDocuments(),
            'Troubleshooting & maintenance' => getSkilledTechnicalTesdaDocuments(),
            'Problem-solving & efficiency' => getSkilledTechnicalTesdaDocuments(),
            'Bricklaying, blockwork, & concrete work' => getSkilledTechnicalTesdaDocuments(),
            'Reading construction plans & specifications' => getSkilledTechnicalTesdaDocuments(),
            'Mixing & applying building materials' => getSkilledTechnicalTesdaDocuments(),
            'Safety & site compliance' => getSkilledTechnicalTesdaDocuments(),
            'Precision & teamwork' => getSkilledTechnicalTesdaDocuments(),
            'Installation & maintenance of HVAC systems' => getSkilledTechnicalTesdaDocuments(),
            'Troubleshooting heating, cooling, and ventilation equipment' => getSkilledTechnicalTesdaDocuments(),
            'Electrical & mechanical knowledge' => getSkilledTechnicalTesdaDocuments(),
            'Safety & compliance with standards' => getSkilledTechnicalTesdaDocuments(),
            'Customer service & communication' => getSkilledTechnicalTesdaDocuments(),
            'Operating CNC machines' => getSkilledTechnicalTesdaDocuments(),
            'Reading technical drawings & G-code' => getSkilledTechnicalTesdaDocuments(),
            'Machine setup & maintenance' => getSkilledTechnicalTesdaDocuments(),
            'Precision measurement & quality control' => getSkilledTechnicalTesdaDocuments(),
            'Maintenance & repair of industrial equipment' => getSkilledTechnicalTesdaDocuments(),
            'Mechanical & electrical troubleshooting' => getSkilledTechnicalTesdaDocuments(),
            'Preventive maintenance procedures' => getSkilledTechnicalTesdaDocuments(),
            'Safety & compliance knowledge' => getSkilledTechnicalTesdaDocuments(),
            'Technical documentation & reporting' => getSkilledTechnicalTesdaDocuments(),
            'Circuit analysis & repair' => getSkilledTechnicalTesdaDocuments(),
            'Testing & troubleshooting electronic equipment' => getSkilledTechnicalTesdaDocuments(),
            'Reading schematics & technical diagrams' => getSkilledTechnicalTesdaDocuments(),
            'Soldering & assembly skills' => getSkilledTechnicalTesdaDocuments(),
            'Attention to detail & problem-solving' => getSkilledTechnicalTesdaDocuments(),
            'Installation & repair of refrigeration systems' => getSkilledTechnicalTesdaDocuments(),
            'Electrical & mechanical troubleshooting' => getSkilledTechnicalTesdaDocuments(),
            'Safety & environmental compliance' => getSkilledTechnicalTesdaDocuments(),
            'Preventive maintenance & testing' => getSkilledTechnicalTesdaDocuments(),
            'Customer service & technical reporting' => getSkilledTechnicalTesdaDocuments(),
            'Operating lathes, mills, and other machining tools' => getSkilledTechnicalTesdaDocuments(),
            'Reading technical drawings & specifications' => getSkilledTechnicalTesdaDocuments(),
            'Material selection & tooling knowledge' => getSkilledTechnicalTesdaDocuments(),
            'Problem-solving & manual dexterity' => getSkilledTechnicalTesdaDocuments(),
            'Welding & cutting techniques' => getSkilledTechnicalTesdaDocuments(),
            'Equipment & tool operation' => getSkilledTechnicalTesdaDocuments(),
            'Safety compliance & precision' => getSkilledTechnicalTesdaDocuments(),
            'Installing & repairing piping systems' => getSkilledTechnicalTesdaDocuments(),
            'Reading technical drawings & schematics' => getSkilledTechnicalTesdaDocuments(),
            'Welding, cutting, & fitting pipes' => getSkilledTechnicalTesdaDocuments(),
            'Safety compliance & pressure testing' => getSkilledTechnicalTesdaDocuments(),
            'Problem-solving & teamwork' => getSkilledTechnicalTesdaDocuments(),
            'Equipment maintenance & troubleshooting' => getSkilledTechnicalTesdaDocuments(),
            'Mechanical & electrical repair' => getSkilledTechnicalTesdaDocuments(),
            'Preventive maintenance scheduling' => getSkilledTechnicalTesdaDocuments(),
            'Problem-solving & documentation' => getSkilledTechnicalTesdaDocuments(),
            'Designing & creating molds, dies, and tools' => getSkilledTechnicalTesdaDocuments(),
            'Precision machining & metalworking' => getSkilledTechnicalTesdaDocuments(),
            'Quality control & measurement' => getSkilledTechnicalTesdaDocuments(),
            
            // Agriculture / Fisheries
            'Farm operations planning & management' => getAgricultureFisheriesDocuments(),
            'Staff supervision & coordination' => getAgricultureFisheriesDocuments(),
            'Crop & livestock management' => getAgricultureFisheriesDocuments(),
            'Budgeting & resource allocation' => getAgricultureFisheriesDocuments(),
            'Problem-solving & decision-making' => getAgricultureFisheriesDocuments(),
            'Crop science & soil management' => getAgricultureFisheriesDocuments(),
            'Fertilizer & pest management' => getAgricultureFisheriesDocuments(),
            'Field research & data analysis' => getAgricultureFisheriesDocuments(),
            'Report writing & documentation' => getAgricultureFisheriesDocuments(),
            'Communication & advisory skills' => getAgricultureFisheriesDocuments(),
            'Aquaculture & fish farm management' => getAgricultureFisheriesDocuments(),
            'Water quality monitoring' => getAgricultureFisheriesDocuments(),
            'Feeding & breeding programs' => getAgricultureFisheriesDocuments(),
            'Equipment operation & maintenance' => getAgricultureFisheriesDocuments(),
            'Record keeping & reporting' => getAgricultureFisheriesDocuments(),
            'Planting, harvesting & basic crop care' => getAgricultureFisheriesDocuments(),
            'Operating basic farm equipment' => getAgricultureFisheriesDocuments(),
            'Irrigation & soil preparation' => getAgricultureFisheriesDocuments(),
            'Manual labor & physical stamina' => getAgricultureFisheriesDocuments(),
            'Following instructions & teamwork' => getAgricultureFisheriesDocuments(),
            'Crop management & pest control' => getAgricultureFisheriesDocuments(),
            'Soil testing & nutrient management' => getAgricultureFisheriesDocuments(),
            'Crop monitoring & reporting' => getAgricultureFisheriesDocuments(),
            'Research & advisory services' => getAgricultureFisheriesDocuments(),
            'Analytical & problem-solving skills' => getAgricultureFisheriesDocuments(),
            'Animal care & feeding' => getAgricultureFisheriesDocuments(),
            'Health monitoring & vaccination' => getAgricultureFisheriesDocuments(),
            'Breeding & herd management' => getAgricultureFisheriesDocuments(),
            'Safety & hygiene compliance' => getAgricultureFisheriesDocuments(),
            'Operating tractors, harvesters, and machinery' => getAgricultureFisheriesDocuments(),
            'Basic maintenance & troubleshooting' => getAgricultureFisheriesDocuments(),
            'Field preparation & cultivation' => getAgricultureFisheriesDocuments(),
            'Safety & compliance' => getAgricultureFisheriesDocuments(),
            'Time management & efficiency' => getAgricultureFisheriesDocuments(),
            'Advising farmers on best practices' => getAgricultureFisheriesDocuments(),
            'Conducting training & workshops' => getAgricultureFisheriesDocuments(),
            'Data collection & reporting' => getAgricultureFisheriesDocuments(),
            'Communication & public engagement' => getAgricultureFisheriesDocuments(),
            'Problem-solving & advisory skills' => getAgricultureFisheriesDocuments(),
            'Plant cultivation & garden management' => getAgricultureFisheriesDocuments(),
            'Pest & disease management' => getAgricultureFisheriesDocuments(),
            'Soil & nutrient analysis' => getAgricultureFisheriesDocuments(),
            'Landscape design & plant selection' => getAgricultureFisheriesDocuments(),
            'Observation & documentation' => getAgricultureFisheriesDocuments(),
            'Fish & aquatic species management' => getAgricultureFisheriesDocuments(),
            'Water quality & environmental monitoring' => getAgricultureFisheriesDocuments(),
            'Feeding, breeding & health management' => getAgricultureFisheriesDocuments(),
            'Technical & analytical skills' => getAgricultureFisheriesDocuments(),
            'Supervising plantation operations & staff' => getAgricultureFisheriesDocuments(),
            'Crop production monitoring' => getAgricultureFisheriesDocuments(),
            'Inventory & resource management' => getAgricultureFisheriesDocuments(),
            'Compliance with safety & environmental regulations' => getAgricultureFisheriesDocuments(),
            'Communication & team coordination' => getAgricultureFisheriesDocuments(),
            'Inspecting farms for compliance & quality' => getAgricultureFisheriesDocuments(),
            'Assessing crop & livestock conditions' => getAgricultureFisheriesDocuments(),
            'Reporting & documentation' => getAgricultureFisheriesDocuments(),
            'Knowledge of agricultural regulations' => getAgricultureFisheriesDocuments(),
            'Attention to detail & analytical skills' => getAgricultureFisheriesDocuments(),
            'Soil analysis & testing' => getAgricultureFisheriesDocuments(),
            'Nutrient management & recommendations' => getAgricultureFisheriesDocuments(),
            'Research & field studies' => getAgricultureFisheriesDocuments(),
            'Reporting & data documentation' => getAgricultureFisheriesDocuments(),
            'Problem-solving & analytical skills' => getAgricultureFisheriesDocuments(),
            'Assisting with crop & livestock management' => getAgricultureFisheriesDocuments(),
            'Field data collection & reporting' => getAgricultureFisheriesDocuments(),
            'Pest & disease monitoring' => getAgricultureFisheriesDocuments(),
            'Teamwork & technical skills' => getAgricultureFisheriesDocuments(),
            
            // Freelance / Online / Remote
            'Administrative support & scheduling' => getFreelanceOnlineRemoteDocuments(),
            'Email & calendar management' => getFreelanceOnlineRemoteDocuments(),
            'Data entry & document preparation' => getFreelanceOnlineRemoteDocuments(),
            'Communication & professionalism' => getFreelanceOnlineRemoteDocuments(),
            'Time management & multitasking' => getFreelanceOnlineRemoteDocuments(),
            'Writing & editing skills' => getFreelanceOnlineRemoteDocuments(),
            'Research & content creation' => getFreelanceOnlineRemoteDocuments(),
            'SEO & digital content knowledge' => getFreelanceOnlineRemoteDocuments(),
            'Meeting deadlines & time management' => getFreelanceOnlineRemoteDocuments(),
            'Communication & adaptability' => getFreelanceOnlineRemoteDocuments(),
            'Subject matter expertise' => getFreelanceOnlineRemoteDocuments(),
            'Lesson planning & curriculum delivery' => getFreelanceOnlineRemoteDocuments(),
            'Virtual teaching tools (Zoom, Google Meet, LMS)' => getFreelanceOnlineRemoteDocuments(),
            'Communication & patience' => getFreelanceOnlineRemoteDocuments(),
            'Feedback & performance tracking' => getFreelanceOnlineRemoteDocuments(),
            'Adobe Creative Suite / Figma / Canva' => getFreelanceOnlineRemoteDocuments(),
            'Branding & visual communication' => getFreelanceOnlineRemoteDocuments(),
            'Creativity & conceptual thinking' => getFreelanceOnlineRemoteDocuments(),
            'Time management & meeting deadlines' => getFreelanceOnlineRemoteDocuments(),
            'Communication & client collaboration' => getFreelanceOnlineRemoteDocuments(),
            'Social media content development' => getFreelanceOnlineRemoteDocuments(),
            'Video/graphic editing skills' => getFreelanceOnlineRemoteDocuments(),
            'Storytelling & creativity' => getFreelanceOnlineRemoteDocuments(),
            'Audience engagement & analytics' => getFreelanceOnlineRemoteDocuments(),
            'Time management & self-discipline' => getFreelanceOnlineRemoteDocuments(),
            'Social media strategy & planning' => getFreelanceOnlineRemoteDocuments(),
            'Content creation & scheduling tools' => getFreelanceOnlineRemoteDocuments(),
            'Analytics & performance tracking' => getFreelanceOnlineRemoteDocuments(),
            'Communication & customer engagement' => getFreelanceOnlineRemoteDocuments(),
            'Creativity & trend awareness' => getFreelanceOnlineRemoteDocuments(),
            'HTML, CSS, JavaScript & frameworks' => getFreelanceOnlineRemoteDocuments(),
            'Responsive design & UX/UI knowledge' => getFreelanceOnlineRemoteDocuments(),
            'Debugging & problem-solving' => getFreelanceOnlineRemoteDocuments(),
            'Version control & collaboration tools (Git)' => getFreelanceOnlineRemoteDocuments(),
            'Communication & project coordination' => getFreelanceOnlineRemoteDocuments(),
            'Accurate data input & verification' => getFreelanceOnlineRemoteDocuments(),
            'Spreadsheet & database management' => getFreelanceOnlineRemoteDocuments(),
            'Attention to detail & accuracy' => getFreelanceOnlineRemoteDocuments(),
            'Time management & efficiency' => getFreelanceOnlineRemoteDocuments(),
            'Basic computer literacy' => getFreelanceOnlineRemoteDocuments(),
            'Fluency in source & target languages' => getFreelanceOnlineRemoteDocuments(),
            'Grammar & writing accuracy' => getFreelanceOnlineRemoteDocuments(),
            'Cultural understanding & localization' => getFreelanceOnlineRemoteDocuments(),
            'Time management & deadlines' => getFreelanceOnlineRemoteDocuments(),
            'Communication & research skills' => getFreelanceOnlineRemoteDocuments(),
            'Customer service & problem-solving' => getFreelanceOnlineRemoteDocuments(),
            'Communication via email, chat, or call' => getFreelanceOnlineRemoteDocuments(),
            'CRM & support software proficiency' => getFreelanceOnlineRemoteDocuments(),
            'Patience & empathy' => getFreelanceOnlineRemoteDocuments(),
            'Client communication & advisory skills' => getFreelanceOnlineRemoteDocuments(),
            'Virtual presentation & collaboration tools' => getFreelanceOnlineRemoteDocuments(),
            'Problem-solving & strategic planning' => getFreelanceOnlineRemoteDocuments(),
            'SEO strategy & keyword research' => getFreelanceOnlineRemoteDocuments(),
            'Google Analytics & Search Console' => getFreelanceOnlineRemoteDocuments(),
            'Content optimization & link building' => getFreelanceOnlineRemoteDocuments(),
            'Reporting & performance tracking' => getFreelanceOnlineRemoteDocuments(),
            'Social media marketing & ads management' => getFreelanceOnlineRemoteDocuments(),
            'Content creation & SEO knowledge' => getFreelanceOnlineRemoteDocuments(),
            'Client communication & reporting' => getFreelanceOnlineRemoteDocuments(),
            'Creativity & time management' => getFreelanceOnlineRemoteDocuments(),
            'Video editing software (Premiere, Final Cut, After Effects)' => getFreelanceOnlineRemoteDocuments(),
            'Storyboarding & sequencing' => getFreelanceOnlineRemoteDocuments(),
            'Color correction & sound editing' => getFreelanceOnlineRemoteDocuments(),
            'Creativity & attention to detail' => getFreelanceOnlineRemoteDocuments(),
            'Meeting deadlines & client communication' => getFreelanceOnlineRemoteDocuments(),
            
            // Legal / Government / Public Service
            'Legal research & analysis' => getLegalGovernmentPublicServiceDocuments(),
            'Drafting contracts & legal documents' => getLegalGovernmentPublicServiceDocuments(),
            'Court representation & litigation' => getLegalGovernmentPublicServiceDocuments(),
            'Negotiation & advocacy skills' => getLegalGovernmentPublicServiceDocuments(),
            'Critical thinking & problem-solving' => getLegalGovernmentPublicServiceDocuments(),
            'Legal research & case preparation' => getLegalGovernmentPublicServiceDocuments(),
            'Drafting & reviewing documents' => getLegalGovernmentPublicServiceDocuments(),
            'Knowledge of legal procedures & terminology' => getLegalGovernmentPublicServiceDocuments(),
            'Organization & record keeping' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & attention to detail' => getLegalGovernmentPublicServiceDocuments(),
            'Policy implementation & regulatory compliance' => getLegalGovernmentPublicServiceDocuments(),
            'Administrative & operational planning' => getLegalGovernmentPublicServiceDocuments(),
            'Public service & stakeholder coordination' => getLegalGovernmentPublicServiceDocuments(),
            'Report writing & documentation' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & problem-solving' => getLegalGovernmentPublicServiceDocuments(),
            'Document preparation & filing' => getLegalGovernmentPublicServiceDocuments(),
            'Case research & organization' => getLegalGovernmentPublicServiceDocuments(),
            'Scheduling & administrative support' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & teamwork' => getLegalGovernmentPublicServiceDocuments(),
            'Attention to detail & confidentiality' => getLegalGovernmentPublicServiceDocuments(),
            'Policy research & evaluation' => getLegalGovernmentPublicServiceDocuments(),
            'Data analysis & report writing' => getLegalGovernmentPublicServiceDocuments(),
            'Strategic thinking & problem-solving' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & stakeholder engagement' => getLegalGovernmentPublicServiceDocuments(),
            'Knowledge of public policy frameworks' => getLegalGovernmentPublicServiceDocuments(),
            'Case filing & document management' => getLegalGovernmentPublicServiceDocuments(),
            'Scheduling hearings & court events' => getLegalGovernmentPublicServiceDocuments(),
            'Knowledge of court procedures & regulations' => getLegalGovernmentPublicServiceDocuments(),
            'Attention to detail & record keeping' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & organizational skills' => getLegalGovernmentPublicServiceDocuments(),
            'Regulatory & legal compliance monitoring' => getLegalGovernmentPublicServiceDocuments(),
            'Policy development & implementation' => getLegalGovernmentPublicServiceDocuments(),
            'Risk assessment & mitigation' => getLegalGovernmentPublicServiceDocuments(),
            'Reporting & documentation' => getLegalGovernmentPublicServiceDocuments(),
            'Analytical thinking & attention to detail' => getLegalGovernmentPublicServiceDocuments(),
            'Administrative & organizational management' => getLegalGovernmentPublicServiceDocuments(),
            'Policy implementation & evaluation' => getLegalGovernmentPublicServiceDocuments(),
            'Budgeting & resource management' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & leadership' => getLegalGovernmentPublicServiceDocuments(),
            'Problem-solving & decision-making' => getLegalGovernmentPublicServiceDocuments(),
            'Conducting case law & statutory research' => getLegalGovernmentPublicServiceDocuments(),
            'Drafting legal memoranda & reports' => getLegalGovernmentPublicServiceDocuments(),
            'Analytical & critical thinking' => getLegalGovernmentPublicServiceDocuments(),
            'Attention to detail & documentation' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & collaboration' => getLegalGovernmentPublicServiceDocuments(),
            'Advising clients on legal matters' => getLegalGovernmentPublicServiceDocuments(),
            'Risk assessment & compliance guidance' => getLegalGovernmentPublicServiceDocuments(),
            'Contract review & negotiation' => getLegalGovernmentPublicServiceDocuments(),
            'Research & analytical skills' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & professionalism' => getLegalGovernmentPublicServiceDocuments(),
            'Assisting judges with case research & drafting' => getLegalGovernmentPublicServiceDocuments(),
            'Legal document preparation' => getLegalGovernmentPublicServiceDocuments(),
            'Court procedure knowledge' => getLegalGovernmentPublicServiceDocuments(),
            'Analytical & research skills' => getLegalGovernmentPublicServiceDocuments(),
            'Confidentiality & attention to detail' => getLegalGovernmentPublicServiceDocuments(),
            'Policy development & evaluation' => getLegalGovernmentPublicServiceDocuments(),
            'Research & data analysis' => getLegalGovernmentPublicServiceDocuments(),
            'Stakeholder engagement & communication' => getLegalGovernmentPublicServiceDocuments(),
            'Project management & planning' => getLegalGovernmentPublicServiceDocuments(),
            'Maintaining court security & order' => getLegalGovernmentPublicServiceDocuments(),
            'Managing courtroom procedures' => getLegalGovernmentPublicServiceDocuments(),
            'Knowledge of legal protocols' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & interpersonal skills' => getLegalGovernmentPublicServiceDocuments(),
            'Attention to detail & reliability' => getLegalGovernmentPublicServiceDocuments(),
            'Reviewing regulations & administrative laws' => getLegalGovernmentPublicServiceDocuments(),
            'Compliance monitoring & enforcement' => getLegalGovernmentPublicServiceDocuments(),
            'Legal research & documentation' => getLegalGovernmentPublicServiceDocuments(),
            'Analytical thinking & problem-solving' => getLegalGovernmentPublicServiceDocuments(),
            'Communication & report writing' => getLegalGovernmentPublicServiceDocuments(),
            
            // Maritime / Aviation / Transport Specialized
            'Navigation & ship handling' => getMaritimeAviationTransportSpecializedDocuments(),
            'Crew management & leadership' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety & emergency procedures' => getMaritimeAviationTransportSpecializedDocuments(),
            'Voyage planning & logistics' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication & decision-making' => getMaritimeAviationTransportSpecializedDocuments(),
            'Aircraft operation & flight navigation' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety protocols & emergency handling' => getMaritimeAviationTransportSpecializedDocuments(),
            'Flight planning & weather assessment' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication with control towers & crew' => getMaritimeAviationTransportSpecializedDocuments(),
            'Decision-making & situational awareness' => getMaritimeAviationTransportSpecializedDocuments(),
            'Passenger safety & emergency procedures' => getMaritimeAviationTransportSpecializedDocuments(),
            'Customer service & communication' => getMaritimeAviationTransportSpecializedDocuments(),
            'In-flight service & hospitality' => getMaritimeAviationTransportSpecializedDocuments(),
            'Conflict resolution & teamwork' => getMaritimeAviationTransportSpecializedDocuments(),
            'First aid & safety compliance' => getMaritimeAviationTransportSpecializedDocuments(),
            'Ship machinery & propulsion systems maintenance' => getMaritimeAviationTransportSpecializedDocuments(),
            'Technical troubleshooting & repair' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety & compliance with maritime regulations' => getMaritimeAviationTransportSpecializedDocuments(),
            'Equipment monitoring & documentation' => getMaritimeAviationTransportSpecializedDocuments(),
            'Team coordination & problem-solving' => getMaritimeAviationTransportSpecializedDocuments(),
            'Navigation & ship deck operations' => getMaritimeAviationTransportSpecializedDocuments(),
            'Cargo handling & stowage planning' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety & emergency drills' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication with crew & port authorities' => getMaritimeAviationTransportSpecializedDocuments(),
            'Record keeping & compliance' => getMaritimeAviationTransportSpecializedDocuments(),
            'Aircraft monitoring & coordination' => getMaritimeAviationTransportSpecializedDocuments(),
            'Airspace management & communication' => getMaritimeAviationTransportSpecializedDocuments(),
            'Problem-solving & quick decision-making' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety & emergency handling' => getMaritimeAviationTransportSpecializedDocuments(),
            'Attention to detail & situational awareness' => getMaritimeAviationTransportSpecializedDocuments(),
            'Maintenance of ship engines & technical systems' => getMaritimeAviationTransportSpecializedDocuments(),
            'Troubleshooting & repair' => getMaritimeAviationTransportSpecializedDocuments(),
            'Compliance with maritime safety regulations' => getMaritimeAviationTransportSpecializedDocuments(),
            'Monitoring performance & documentation' => getMaritimeAviationTransportSpecializedDocuments(),
            'Passenger safety & assistance' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication & conflict resolution' => getMaritimeAviationTransportSpecializedDocuments(),
            'Emergency response & first aid' => getMaritimeAviationTransportSpecializedDocuments(),
            'Teamwork & professionalism' => getMaritimeAviationTransportSpecializedDocuments(),
            'Maintenance & repair of marine equipment' => getMaritimeAviationTransportSpecializedDocuments(),
            'Technical troubleshooting & inspections' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety & compliance with regulations' => getMaritimeAviationTransportSpecializedDocuments(),
            'Documentation & reporting' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety policy development & compliance' => getMaritimeAviationTransportSpecializedDocuments(),
            'Risk assessment & hazard identification' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety audits & reporting' => getMaritimeAviationTransportSpecializedDocuments(),
            'Emergency preparedness & training' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication & coordination' => getMaritimeAviationTransportSpecializedDocuments(),
            'Port operations management' => getMaritimeAviationTransportSpecializedDocuments(),
            'Cargo & vessel documentation' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety & regulatory compliance' => getMaritimeAviationTransportSpecializedDocuments(),
            'Coordination with shipping agents & authorities' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication & problem-solving' => getMaritimeAviationTransportSpecializedDocuments(),
            'Supervision of port & harbor operations' => getMaritimeAviationTransportSpecializedDocuments(),
            'Vessel traffic management & navigation safety' => getMaritimeAviationTransportSpecializedDocuments(),
            'Compliance with maritime laws & regulations' => getMaritimeAviationTransportSpecializedDocuments(),
            'Coordination with port staff & authorities' => getMaritimeAviationTransportSpecializedDocuments(),
            'Decision-making & leadership' => getMaritimeAviationTransportSpecializedDocuments(),
            'Flight planning & route coordination' => getMaritimeAviationTransportSpecializedDocuments(),
            'Monitoring weather & air traffic' => getMaritimeAviationTransportSpecializedDocuments(),
            'Communication with pilots & ground control' => getMaritimeAviationTransportSpecializedDocuments(),
            'Safety compliance & emergency planning' => getMaritimeAviationTransportSpecializedDocuments(),
            'Analytical thinking & decision-making' => getMaritimeAviationTransportSpecializedDocuments(),
            
            // Science / Research / Environment
            'Experimental design & methodology' => getScienceResearchEnvironmentDocuments(),
            'Data collection & analysis' => getScienceResearchEnvironmentDocuments(),
            'Scientific writing & reporting' => getScienceResearchEnvironmentDocuments(),
            'Critical thinking & problem-solving' => getScienceResearchEnvironmentDocuments(),
            'Laboratory & field research techniques' => getScienceResearchEnvironmentDocuments(),
            'Sample preparation & testing' => getScienceResearchEnvironmentDocuments(),
            'Equipment operation & maintenance' => getScienceResearchEnvironmentDocuments(),
            'Recording & documenting results' => getScienceResearchEnvironmentDocuments(),
            'Quality control & compliance with protocols' => getScienceResearchEnvironmentDocuments(),
            'Attention to detail & accuracy' => getScienceResearchEnvironmentDocuments(),
            'Environmental monitoring & assessment' => getScienceResearchEnvironmentDocuments(),
            'Compliance with environmental regulations' => getScienceResearchEnvironmentDocuments(),
            'Data collection & reporting' => getScienceResearchEnvironmentDocuments(),
            'Risk assessment & mitigation' => getScienceResearchEnvironmentDocuments(),
            'Communication & stakeholder coordination' => getScienceResearchEnvironmentDocuments(),
            'Data collection & cleaning' => getScienceResearchEnvironmentDocuments(),
            'Statistical analysis & interpretation' => getScienceResearchEnvironmentDocuments(),
            'Visualization & reporting tools (Excel, Tableau, Python, R)' => getScienceResearchEnvironmentDocuments(),
            'Problem-solving & decision support' => getScienceResearchEnvironmentDocuments(),
            'Communication of findings to stakeholders' => getScienceResearchEnvironmentDocuments(),
            'Conducting biochemical experiments' => getScienceResearchEnvironmentDocuments(),
            'Laboratory techniques & instrumentation' => getScienceResearchEnvironmentDocuments(),
            'Data analysis & interpretation' => getScienceResearchEnvironmentDocuments(),
            'Report writing & documentation' => getScienceResearchEnvironmentDocuments(),
            'Attention to detail & critical thinking' => getScienceResearchEnvironmentDocuments(),
            'Field research & ecological surveys' => getScienceResearchEnvironmentDocuments(),
            'Environmental data collection & analysis' => getScienceResearchEnvironmentDocuments(),
            'Species & habitat monitoring' => getScienceResearchEnvironmentDocuments(),
            'Report writing & scientific documentation' => getScienceResearchEnvironmentDocuments(),
            'Analytical & observational skills' => getScienceResearchEnvironmentDocuments(),
            'Conducting surveys & experiments in the field' => getScienceResearchEnvironmentDocuments(),
            'Data collection & recording' => getScienceResearchEnvironmentDocuments(),
            'Equipment handling & sample preservation' => getScienceResearchEnvironmentDocuments(),
            'Observation & analytical skills' => getScienceResearchEnvironmentDocuments(),
            'Teamwork & communication' => getScienceResearchEnvironmentDocuments(),
            'Culturing & analyzing microorganisms' => getScienceResearchEnvironmentDocuments(),
            'Laboratory safety & sterilization protocols' => getScienceResearchEnvironmentDocuments(),
            'Data analysis & experimental documentation' => getScienceResearchEnvironmentDocuments(),
            'Use of laboratory instruments & techniques' => getScienceResearchEnvironmentDocuments(),
            'Environmental assessment & reporting' => getScienceResearchEnvironmentDocuments(),
            'Regulatory compliance & advisory services' => getScienceResearchEnvironmentDocuments(),
            'Risk analysis & mitigation planning' => getScienceResearchEnvironmentDocuments(),
            'Project management & coordination' => getScienceResearchEnvironmentDocuments(),
            'Communication & technical writing' => getScienceResearchEnvironmentDocuments(),
            'Supporting laboratory experiments' => getScienceResearchEnvironmentDocuments(),
            'Preparing samples & reagents' => getScienceResearchEnvironmentDocuments(),
            'Equipment cleaning & maintenance' => getScienceResearchEnvironmentDocuments(),
            'Record keeping & documentation' => getScienceResearchEnvironmentDocuments(),
            'Following instructions & safety protocols' => getScienceResearchEnvironmentDocuments(),
            'Assisting with experimental design & execution' => getScienceResearchEnvironmentDocuments(),
            'Literature review & documentation' => getScienceResearchEnvironmentDocuments(),
            'Laboratory or field support' => getScienceResearchEnvironmentDocuments(),
            'Teamwork & organizational skills' => getScienceResearchEnvironmentDocuments(),
            'Studying marine ecosystems & species' => getScienceResearchEnvironmentDocuments(),
            'Field research & sample collection' => getScienceResearchEnvironmentDocuments(),
            'Laboratory analysis & data interpretation' => getScienceResearchEnvironmentDocuments(),
            'Report writing & presentation' => getScienceResearchEnvironmentDocuments(),
            'Observational & analytical skills' => getScienceResearchEnvironmentDocuments(),
            'Performing laboratory tests & assays' => getScienceResearchEnvironmentDocuments(),
            'Quality control & compliance' => getScienceResearchEnvironmentDocuments(),
            'Data analysis & reporting' => getScienceResearchEnvironmentDocuments(),
            'Operating lab equipment' => getScienceResearchEnvironmentDocuments(),
            'Attention to detail & problem-solving' => getScienceResearchEnvironmentDocuments(),
            'Climate data collection & modeling' => getScienceResearchEnvironmentDocuments(),
            'Environmental & atmospheric research' => getScienceResearchEnvironmentDocuments(),
            'Statistical & computational analysis' => getScienceResearchEnvironmentDocuments(),
            'Report writing & policy recommendation' => getScienceResearchEnvironmentDocuments(),
            'Critical thinking & scientific communication' => getScienceResearchEnvironmentDocuments(),
            
            // Arts / Entertainment / Culture
            'Acting & performance techniques' => getArtsEntertainmentCultureDocuments(),
            'Script memorization & interpretation' => getArtsEntertainmentCultureDocuments(),
            'Emotional expression & body language' => getArtsEntertainmentCultureDocuments(),
            'Collaboration & teamwork' => getArtsEntertainmentCultureDocuments(),
            'Communication & adaptability' => getArtsEntertainmentCultureDocuments(),
            'Instrument proficiency or vocal skills' => getArtsEntertainmentCultureDocuments(),
            'Music theory & composition' => getArtsEntertainmentCultureDocuments(),
            'Performance & stage presence' => getArtsEntertainmentCultureDocuments(),
            'Collaboration & ensemble work' => getArtsEntertainmentCultureDocuments(),
            'Creativity & practice discipline' => getArtsEntertainmentCultureDocuments(),
            'Dance technique & choreography execution' => getArtsEntertainmentCultureDocuments(),
            'Physical fitness & flexibility' => getArtsEntertainmentCultureDocuments(),
            'Stage presence & performance skills' => getArtsEntertainmentCultureDocuments(),
            'Teamwork & collaboration' => getArtsEntertainmentCultureDocuments(),
            'Discipline & practice commitment' => getArtsEntertainmentCultureDocuments(),
            'Event planning & organization' => getArtsEntertainmentCultureDocuments(),
            'Cultural knowledge & program design' => getArtsEntertainmentCultureDocuments(),
            'Communication & stakeholder engagement' => getArtsEntertainmentCultureDocuments(),
            'Budgeting & resource management' => getArtsEntertainmentCultureDocuments(),
            'Leadership & team coordination' => getArtsEntertainmentCultureDocuments(),
            'Vocal technique & control' => getArtsEntertainmentCultureDocuments(),
            'Music interpretation & performance' => getArtsEntertainmentCultureDocuments(),
            'Stage presence & audience engagement' => getArtsEntertainmentCultureDocuments(),
            'Collaboration & rehearsals' => getArtsEntertainmentCultureDocuments(),
            'Discipline & practice' => getArtsEntertainmentCultureDocuments(),
            'Creative vision & storytelling' => getArtsEntertainmentCultureDocuments(),
            'Team leadership & coordination' => getArtsEntertainmentCultureDocuments(),
            'Script analysis & interpretation' => getArtsEntertainmentCultureDocuments(),
            'Communication & problem-solving' => getArtsEntertainmentCultureDocuments(),
            'Project management & scheduling' => getArtsEntertainmentCultureDocuments(),
            'Camera operation & photography techniques' => getArtsEntertainmentCultureDocuments(),
            'Composition & lighting skills' => getArtsEntertainmentCultureDocuments(),
            'Photo editing & post-processing' => getArtsEntertainmentCultureDocuments(),
            'Creativity & artistic vision' => getArtsEntertainmentCultureDocuments(),
            'Communication & client coordination' => getArtsEntertainmentCultureDocuments(),
            'Art history knowledge & research' => getArtsEntertainmentCultureDocuments(),
            'Exhibition planning & design' => getArtsEntertainmentCultureDocuments(),
            'Collection management & documentation' => getArtsEntertainmentCultureDocuments(),
            'Communication & public engagement' => getArtsEntertainmentCultureDocuments(),
            'Attention to detail & organizational skills' => getArtsEntertainmentCultureDocuments(),
            'Acting & performance skills' => getArtsEntertainmentCultureDocuments(),
            'Stage presence & voice projection' => getArtsEntertainmentCultureDocuments(),
            'Memorization & improvisation' => getArtsEntertainmentCultureDocuments(),
            'Discipline & rehearsal commitment' => getArtsEntertainmentCultureDocuments(),
            'Fashion & costume design' => getArtsEntertainmentCultureDocuments(),
            'Sewing & garment construction' => getArtsEntertainmentCultureDocuments(),
            'Creativity & concept development' => getArtsEntertainmentCultureDocuments(),
            'Collaboration with directors & performers' => getArtsEntertainmentCultureDocuments(),
            'Time management & project planning' => getArtsEntertainmentCultureDocuments(),
            'Drawing, painting, or sculpting skills' => getArtsEntertainmentCultureDocuments(),
            'Creativity & artistic expression' => getArtsEntertainmentCultureDocuments(),
            'Knowledge of art materials & techniques' => getArtsEntertainmentCultureDocuments(),
            'Portfolio development & presentation' => getArtsEntertainmentCultureDocuments(),
            'Attention to detail & self-discipline' => getArtsEntertainmentCultureDocuments(),
            'Video editing software proficiency (Premiere, Final Cut, DaVinci)' => getArtsEntertainmentCultureDocuments(),
            'Storytelling & narrative pacing' => getArtsEntertainmentCultureDocuments(),
            'Attention to detail & visual continuity' => getArtsEntertainmentCultureDocuments(),
            'Collaboration with directors & production teams' => getArtsEntertainmentCultureDocuments(),
            'Problem-solving & creative thinking' => getArtsEntertainmentCultureDocuments(),
            'Dance composition & choreography design' => getArtsEntertainmentCultureDocuments(),
            'Leadership & teaching dancers' => getArtsEntertainmentCultureDocuments(),
            'Music interpretation & timing' => getArtsEntertainmentCultureDocuments(),
            'Communication & teamwork' => getArtsEntertainmentCultureDocuments(),
            'Event & production planning' => getArtsEntertainmentCultureDocuments(),
            'Team coordination & scheduling' => getArtsEntertainmentCultureDocuments(),
            'Communication with performers & crew' => getArtsEntertainmentCultureDocuments(),
            'Problem-solving & adaptability' => getArtsEntertainmentCultureDocuments(),
            
            // Religion / NGO / Development / Cooperative
            'Spiritual leadership & counseling' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Public speaking & preaching' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Community engagement & mentorship' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Conflict resolution & pastoral care' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Organizational & administrative skills' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Program planning & implementation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Project monitoring & evaluation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Community engagement & stakeholder coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Reporting & documentation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & problem-solving' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Case assessment & client counseling' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Crisis intervention & support' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Advocacy & resource coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & empathy' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Documentation & reporting' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Community engagement & mobilization' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Advocacy & program planning' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Public speaking & facilitation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Leadership & networking' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Problem-solving & coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Community outreach & engagement' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Spiritual guidance & mentorship' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Cross-cultural communication & adaptability' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Program coordination & organization' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Empathy & interpersonal skills' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Fundraising & donor relations' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Stakeholder engagement & networking' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Monitoring & reporting' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & strategic thinking' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Recruitment & training of volunteers' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Scheduling & task delegation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & motivation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Program coordination & support' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Organizational & record-keeping skills' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Administrative & organizational management' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Event planning & coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & stakeholder engagement' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Financial management & budgeting' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Leadership & problem-solving' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Program design & implementation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Team management & coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Monitoring & evaluation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Budgeting & resource allocation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & reporting' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Cooperative operations & management' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Financial & resource management' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Team leadership & coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & member relations' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Problem-solving & decision-making' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Program implementation & monitoring' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Data collection & reporting' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Teamwork & coordination' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Project planning & execution' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Stakeholder engagement & reporting' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & documentation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Needs assessment & program planning' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Community mobilization & engagement' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Communication & facilitation' => getReligionNgoDevelopmentCooperativeDocuments(),
            'Problem-solving & teamwork' => getReligionNgoDevelopmentCooperativeDocuments(),
            
            // Special / Rare Jobs
            'Penetration testing & vulnerability assessment' => getSpecialRareJobsDocuments(),
            'Network & system security analysis' => getSpecialRareJobsDocuments(),
            'Knowledge of cybersecurity tools & protocols' => getSpecialRareJobsDocuments(),
            'Problem-solving & analytical thinking' => getSpecialRareJobsDocuments(),
            'Ethical standards & reporting' => getSpecialRareJobsDocuments(),
            'Physical fitness & agility' => getSpecialRareJobsDocuments(),
            'Stage & camera coordination' => getSpecialRareJobsDocuments(),
            'Risk assessment & safety compliance' => getSpecialRareJobsDocuments(),
            'Acting & performance skills' => getSpecialRareJobsDocuments(),
            'Teamwork & adaptability' => getSpecialRareJobsDocuments(),
            'Sculpture & carving techniques' => getSpecialRareJobsDocuments(),
            'Artistic design & creativity' => getSpecialRareJobsDocuments(),
            'Precision & attention to detail' => getSpecialRareJobsDocuments(),
            'Tool & equipment handling' => getSpecialRareJobsDocuments(),
            'Time management & project planning' => getSpecialRareJobsDocuments(),
            'Gaming strategy & mechanics' => getSpecialRareJobsDocuments(),
            'Hand-eye coordination & reflexes' => getSpecialRareJobsDocuments(),
            'Team communication & coordination' => getSpecialRareJobsDocuments(),
            'Streaming & content creation skills' => getSpecialRareJobsDocuments(),
            'Adaptability & mental focus' => getSpecialRareJobsDocuments(),
            'Puzzle design & game mechanics' => getSpecialRareJobsDocuments(),
            'Creativity & thematic storytelling' => getSpecialRareJobsDocuments(),
            'Project planning & execution' => getSpecialRareJobsDocuments(),
            'Problem-solving & analytical skills' => getSpecialRareJobsDocuments(),
            'Collaboration & user experience design' => getSpecialRareJobsDocuments(),
            'Drone piloting & navigation' => getSpecialRareJobsDocuments(),
            'Aerial photography/videography' => getSpecialRareJobsDocuments(),
            'Equipment maintenance & safety compliance' => getSpecialRareJobsDocuments(),
            'Spatial awareness & technical troubleshooting' => getSpecialRareJobsDocuments(),
            'Regulatory knowledge & reporting' => getSpecialRareJobsDocuments(),
            'Vocal control & modulation' => getSpecialRareJobsDocuments(),
            'Script interpretation & character development' => getSpecialRareJobsDocuments(),
            'Recording & audio editing software proficiency' => getSpecialRareJobsDocuments(),
            'Creativity & performance skills' => getSpecialRareJobsDocuments(),
            'Communication & timing' => getSpecialRareJobsDocuments(),
            'Sport-specific technical skills' => getSpecialRareJobsDocuments(),
            'Safety & risk assessment' => getSpecialRareJobsDocuments(),
            'Instruction & coaching abilities' => getSpecialRareJobsDocuments(),
            'Physical fitness & endurance' => getSpecialRareJobsDocuments(),
            'Communication & motivation' => getSpecialRareJobsDocuments(),
            'Visual effects design & execution' => getSpecialRareJobsDocuments(),
            'Makeup, prosthetics, or CGI skills' => getSpecialRareJobsDocuments(),
            'Creativity & artistic design' => getSpecialRareJobsDocuments(),
            'Collaboration with production teams' => getSpecialRareJobsDocuments(),
            'Technical proficiency & problem-solving' => getSpecialRareJobsDocuments(),
            'Sleight of hand & performance techniques' => getSpecialRareJobsDocuments(),
            'Creativity & show design' => getSpecialRareJobsDocuments(),
            'Audience engagement & stage presence' => getSpecialRareJobsDocuments(),
            'Practice & precision' => getSpecialRareJobsDocuments(),
            'Communication & improvisation' => getSpecialRareJobsDocuments(),
            'Observation & reporting skills' => getSpecialRareJobsDocuments(),
            'Analytical thinking & evaluation' => getSpecialRareJobsDocuments(),
            'Discretion & attention to detail' => getSpecialRareJobsDocuments(),
            'Communication & documentation' => getSpecialRareJobsDocuments(),
            'Time management & reliability' => getSpecialRareJobsDocuments(),
            'Manipulation & control of puppets' => getSpecialRareJobsDocuments(),
            'Acting & storytelling' => getSpecialRareJobsDocuments(),
            'Voice modulation & performance' => getSpecialRareJobsDocuments(),
            'Creativity & artistic expression' => getSpecialRareJobsDocuments(),
            'Coordination & teamwork' => getSpecialRareJobsDocuments(),
            'Facial reconstruction & sketching' => getSpecialRareJobsDocuments(),
            'Observation & attention to detail' => getSpecialRareJobsDocuments(),
            'Knowledge of anatomy & proportions' => getSpecialRareJobsDocuments(),
            'Communication with law enforcement' => getSpecialRareJobsDocuments(),
            'Analytical & technical drawing skills' => getSpecialRareJobsDocuments(),
            
            // Utilities / Public Services
            'Electrical installation, wiring & repair' => getUtilitiesPublicServicesDocuments(),
            'Knowledge of electrical codes & safety regulations' => getUtilitiesPublicServicesDocuments(),
            'Troubleshooting & problem-solving' => getUtilitiesPublicServicesDocuments(),
            'Reading blueprints & technical diagrams' => getUtilitiesPublicServicesDocuments(),
            'Use of hand & power tools' => getUtilitiesPublicServicesDocuments(),
            'Monitoring & operating water treatment systems' => getUtilitiesPublicServicesDocuments(),
            'Chemical dosing & water quality testing' => getUtilitiesPublicServicesDocuments(),
            'Equipment operation & maintenance' => getUtilitiesPublicServicesDocuments(),
            'Compliance with environmental & safety standards' => getUtilitiesPublicServicesDocuments(),
            'Data recording & reporting' => getUtilitiesPublicServicesDocuments(),
            'Maintenance & repair of utility systems' => getUtilitiesPublicServicesDocuments(),
            'Troubleshooting mechanical & electrical issues' => getUtilitiesPublicServicesDocuments(),
            'Equipment operation & monitoring' => getUtilitiesPublicServicesDocuments(),
            'Safety & regulatory compliance' => getUtilitiesPublicServicesDocuments(),
            'Record keeping & reporting' => getUtilitiesPublicServicesDocuments(),
            'Reading utility meters accurately' => getUtilitiesPublicServicesDocuments(),
            'Data collection & entry' => getUtilitiesPublicServicesDocuments(),
            'Attention to detail & reliability' => getUtilitiesPublicServicesDocuments(),
            'Basic knowledge of electrical/water systems' => getUtilitiesPublicServicesDocuments(),
            'Communication & reporting' => getUtilitiesPublicServicesDocuments(),
            'Waste collection & disposal management' => getUtilitiesPublicServicesDocuments(),
            'Recycling & environmental compliance' => getUtilitiesPublicServicesDocuments(),
            'Equipment operation & safety procedures' => getUtilitiesPublicServicesDocuments(),
            'Monitoring & reporting' => getUtilitiesPublicServicesDocuments(),
            'Coordination & teamwork' => getUtilitiesPublicServicesDocuments(),
            'Electrical line installation & maintenance' => getUtilitiesPublicServicesDocuments(),
            'Troubleshooting & repair' => getUtilitiesPublicServicesDocuments(),
            'Safety & compliance with regulations' => getUtilitiesPublicServicesDocuments(),
            'Physical fitness & use of specialized tools' => getUtilitiesPublicServicesDocuments(),
            'Team coordination & problem-solving' => getUtilitiesPublicServicesDocuments(),
            'Design & maintenance of public utility systems' => getUtilitiesPublicServicesDocuments(),
            'Project planning & implementation' => getUtilitiesPublicServicesDocuments(),
            'Technical analysis & troubleshooting' => getUtilitiesPublicServicesDocuments(),
            'Compliance with safety & environmental regulations' => getUtilitiesPublicServicesDocuments(),
            'Communication & documentation' => getUtilitiesPublicServicesDocuments(),
            'Preventive & corrective maintenance' => getUtilitiesPublicServicesDocuments(),
            'Equipment repair & troubleshooting' => getUtilitiesPublicServicesDocuments(),
            'Mechanical & electrical knowledge' => getUtilitiesPublicServicesDocuments(),
            'Safety compliance & risk management' => getUtilitiesPublicServicesDocuments(),
            'Facility operations & maintenance management' => getUtilitiesPublicServicesDocuments(),
            'Scheduling & supervising maintenance tasks' => getUtilitiesPublicServicesDocuments(),
            'Safety & compliance monitoring' => getUtilitiesPublicServicesDocuments(),
            'Vendor & contractor coordination' => getUtilitiesPublicServicesDocuments(),
            'Documentation & reporting' => getUtilitiesPublicServicesDocuments(),
            'Monitoring & maintenance of energy systems' => getUtilitiesPublicServicesDocuments(),
            'Problem-solving & technical skills' => getUtilitiesPublicServicesDocuments(),
            'Operating water treatment equipment' => getUtilitiesPublicServicesDocuments(),
            'Monitoring water quality & chemical dosing' => getUtilitiesPublicServicesDocuments(),
            'Maintenance & troubleshooting' => getUtilitiesPublicServicesDocuments(),
            'Compliance with safety & environmental standards' => getUtilitiesPublicServicesDocuments(),
            'Operating turbines, generators & plant systems' => getUtilitiesPublicServicesDocuments(),
            'Monitoring performance & safety systems' => getUtilitiesPublicServicesDocuments(),
            'Troubleshooting & preventive maintenance' => getUtilitiesPublicServicesDocuments(),
            'Compliance with regulations & safety standards' => getUtilitiesPublicServicesDocuments(),
            'Communication & teamwork' => getUtilitiesPublicServicesDocuments(),
            
            // Telecommunications
            'Installation, maintenance & repair of telecom equipment' => getTelecommunicationsDocuments(),
            'Troubleshooting & problem-solving' => getTelecommunicationsDocuments(),
            'Knowledge of network systems & protocols' => getTelecommunicationsDocuments(),
            'Use of hand and diagnostic tools' => getTelecommunicationsDocuments(),
            'Safety & compliance with regulations' => getTelecommunicationsDocuments(),
            'Network design, configuration & optimization' => getTelecommunicationsDocuments(),
            'Troubleshooting & performance monitoring' => getTelecommunicationsDocuments(),
            'Knowledge of routers, switches, firewalls' => getTelecommunicationsDocuments(),
            'Network security & protocols (TCP/IP, VPNs, etc.)' => getTelecommunicationsDocuments(),
            'Documentation & technical reporting' => getTelecommunicationsDocuments(),
            'Technical support & issue resolution' => getTelecommunicationsDocuments(),
            'Knowledge of telecom products & services' => getTelecommunicationsDocuments(),
            'Communication & problem-solving skills' => getTelecommunicationsDocuments(),
            'CRM software proficiency' => getTelecommunicationsDocuments(),
            'Patience & customer service orientation' => getTelecommunicationsDocuments(),
            'On-site installation & maintenance of telecom systems' => getTelecommunicationsDocuments(),
            'Equipment troubleshooting & repair' => getTelecommunicationsDocuments(),
            'Technical documentation & reporting' => getTelecommunicationsDocuments(),
            'Coordination with operations teams' => getTelecommunicationsDocuments(),
            'Safety & regulatory compliance' => getTelecommunicationsDocuments(),
            'Installation & maintenance of telecom towers' => getTelecommunicationsDocuments(),
            'Rigging & climbing safety procedures' => getTelecommunicationsDocuments(),
            'Equipment calibration & troubleshooting' => getTelecommunicationsDocuments(),
            'Team coordination & physical fitness' => getTelecommunicationsDocuments(),
            'Documentation & compliance' => getTelecommunicationsDocuments(),
            'Network monitoring & performance analysis' => getTelecommunicationsDocuments(),
            'Data interpretation & reporting' => getTelecommunicationsDocuments(),
            'Knowledge of telecom infrastructure & protocols' => getTelecommunicationsDocuments(),
            'Problem-solving & optimization' => getTelecommunicationsDocuments(),
            'Communication & documentation' => getTelecommunicationsDocuments(),
            'Fiber optic cable installation & splicing' => getTelecommunicationsDocuments(),
            'Troubleshooting & signal testing' => getTelecommunicationsDocuments(),
            'Equipment handling & calibration' => getTelecommunicationsDocuments(),
            'Safety & compliance with standards' => getTelecommunicationsDocuments(),
            'Documentation & reporting' => getTelecommunicationsDocuments(),
            'Installation & configuration of VoIP systems' => getTelecommunicationsDocuments(),
            'Network troubleshooting & voice quality monitoring' => getTelecommunicationsDocuments(),
            'Knowledge of SIP, codecs, and PBX systems' => getTelecommunicationsDocuments(),
            'Problem-solving & technical support' => getTelecommunicationsDocuments(),
            'Documentation & communication' => getTelecommunicationsDocuments(),
            'Radio frequency design & analysis' => getTelecommunicationsDocuments(),
            'Signal testing & optimization' => getTelecommunicationsDocuments(),
            'Knowledge of antennas, transmitters, and spectrum regulations' => getTelecommunicationsDocuments(),
            'Problem-solving & technical documentation' => getTelecommunicationsDocuments(),
            'Collaboration with network teams' => getTelecommunicationsDocuments(),
            'Scheduling & coordinating service requests' => getTelecommunicationsDocuments(),
            'Customer communication & follow-up' => getTelecommunicationsDocuments(),
            'Monitoring field operations' => getTelecommunicationsDocuments(),
            'Problem-solving & multitasking' => getTelecommunicationsDocuments(),
            'Customer relationship management' => getTelecommunicationsDocuments(),
            'Sales strategy & target achievement' => getTelecommunicationsDocuments(),
            'Communication & negotiation skills' => getTelecommunicationsDocuments(),
            'Market analysis & reporting' => getTelecommunicationsDocuments(),
            'Installation & configuration of network hardware' => getTelecommunicationsDocuments(),
            'Cable management & connectivity testing' => getTelecommunicationsDocuments(),
            'Troubleshooting & technical problem-solving' => getTelecommunicationsDocuments(),
            'Knowledge of LAN/WAN & network protocols' => getTelecommunicationsDocuments(),
            
            // Mining / Geology
            'Geological mapping & field surveys' => getMiningGeologyDocuments(),
            'Mineral & rock analysis' => getMiningGeologyDocuments(),
            'Data collection & interpretation' => getMiningGeologyDocuments(),
            'Report writing & documentation' => getMiningGeologyDocuments(),
            'Knowledge of environmental & safety regulations' => getMiningGeologyDocuments(),
            'Mine design & planning' => getMiningGeologyDocuments(),
            'Equipment selection & operations oversight' => getMiningGeologyDocuments(),
            'Safety & regulatory compliance' => getMiningGeologyDocuments(),
            'Cost estimation & resource management' => getMiningGeologyDocuments(),
            'Problem-solving & project coordination' => getMiningGeologyDocuments(),
            'Operation of drilling machinery & equipment' => getMiningGeologyDocuments(),
            'Drilling techniques & procedures' => getMiningGeologyDocuments(),
            'Maintenance & troubleshooting of equipment' => getMiningGeologyDocuments(),
            'Safety compliance & risk awareness' => getMiningGeologyDocuments(),
            'Recording & reporting of drilling data' => getMiningGeologyDocuments(),
            'Site safety management & inspections' => getMiningGeologyDocuments(),
            'Risk assessment & hazard identification' => getMiningGeologyDocuments(),
            'Compliance with mining regulations & safety protocols' => getMiningGeologyDocuments(),
            'Emergency response planning' => getMiningGeologyDocuments(),
            'Training & communication' => getMiningGeologyDocuments(),
            'Land & mine surveying techniques' => getMiningGeologyDocuments(),
            'Use of surveying instruments (total station, GPS)' => getMiningGeologyDocuments(),
            'Data analysis & mapping' => getMiningGeologyDocuments(),
            'Reporting & documentation' => getMiningGeologyDocuments(),
            'Team coordination & compliance with standards' => getMiningGeologyDocuments(),
            'Equipment operation & maintenance' => getMiningGeologyDocuments(),
            'Monitoring mining processes' => getMiningGeologyDocuments(),
            'Data collection & reporting' => getMiningGeologyDocuments(),
            'Data collection & analysis' => getMiningGeologyDocuments(),
            'Safety compliance & problem-solving' => getMiningGeologyDocuments(),
            'Technical support for mining operations' => getMiningGeologyDocuments(),
            'Soil & rock mechanics analysis' => getMiningGeologyDocuments(),
            'Site investigation & sampling' => getMiningGeologyDocuments(),
            'Slope stability & foundation assessment' => getMiningGeologyDocuments(),
            'Data interpretation & reporting' => getMiningGeologyDocuments(),
            'Mineral sampling & testing' => getMiningGeologyDocuments(),
            'Laboratory analysis techniques' => getMiningGeologyDocuments(),
            'Data recording & interpretation' => getMiningGeologyDocuments(),
            'Report preparation & documentation' => getMiningGeologyDocuments(),
            'Knowledge of environmental & safety standards' => getMiningGeologyDocuments(),
            'Planning & conducting mineral exploration activities' => getMiningGeologyDocuments(),
            'Geological surveys & data collection' => getMiningGeologyDocuments(),
            'Sampling & site assessment' => getMiningGeologyDocuments(),
            'Reporting & mapping' => getMiningGeologyDocuments(),
            'Compliance with safety & environmental regulations' => getMiningGeologyDocuments(),
            'Supervision of quarry operations' => getMiningGeologyDocuments(),
            'Equipment & workforce management' => getMiningGeologyDocuments(),
            'Safety & compliance enforcement' => getMiningGeologyDocuments(),
            'Production monitoring & reporting' => getMiningGeologyDocuments(),
            'Problem-solving & coordination' => getMiningGeologyDocuments(),
            'Mine mapping & surveying' => getMiningGeologyDocuments(),
            'Use of surveying instruments & software' => getMiningGeologyDocuments(),
            'Safety compliance & teamwork' => getMiningGeologyDocuments(),
            'Risk assessment & hazard mitigation' => getMiningGeologyDocuments(),
            'Development of safety protocols & procedures' => getMiningGeologyDocuments(),
            'Compliance with mining regulations & standards' => getMiningGeologyDocuments(),
            'Safety audits & inspections' => getMiningGeologyDocuments(),
            
            // Oil / Gas / Energy
            'Oil & gas reservoir evaluation & planning' => getOilGasEnergyDocuments(),
            'Drilling & production optimization' => getOilGasEnergyDocuments(),
            'Data analysis & simulation' => getOilGasEnergyDocuments(),
            'Project management & cost estimation' => getOilGasEnergyDocuments(),
            'Safety & regulatory compliance' => getOilGasEnergyDocuments(),
            'Site safety management & inspections' => getOilGasEnergyDocuments(),
            'Risk assessment & hazard identification' => getOilGasEnergyDocuments(),
            'Compliance with industry safety standards (OSHA, HSE)' => getOilGasEnergyDocuments(),
            'Emergency response planning' => getOilGasEnergyDocuments(),
            'Training & communication' => getOilGasEnergyDocuments(),
            'Energy data collection & analysis' => getOilGasEnergyDocuments(),
            'Market & consumption trend analysis' => getOilGasEnergyDocuments(),
            'Reporting & visualization (Excel, Power BI, etc.)' => getOilGasEnergyDocuments(),
            'Regulatory compliance awareness' => getOilGasEnergyDocuments(),
            'Problem-solving & strategic recommendations' => getOilGasEnergyDocuments(),
            'Operation of oil, gas, or energy plant equipment' => getOilGasEnergyDocuments(),
            'Monitoring system performance & safety' => getOilGasEnergyDocuments(),
            'Troubleshooting & preventive maintenance' => getOilGasEnergyDocuments(),
            'Compliance with operational & environmental standards' => getOilGasEnergyDocuments(),
            'Reporting & record keeping' => getOilGasEnergyDocuments(),
            'Drilling planning & execution' => getOilGasEnergyDocuments(),
            'Equipment selection & operations oversight' => getOilGasEnergyDocuments(),
            'Drilling optimization & cost management' => getOilGasEnergyDocuments(),
            'Data analysis & reporting' => getOilGasEnergyDocuments(),
            'Preventive & corrective maintenance of plant machinery' => getOilGasEnergyDocuments(),
            'Equipment troubleshooting & repair' => getOilGasEnergyDocuments(),
            'Mechanical & electrical knowledge' => getOilGasEnergyDocuments(),
            'Safety compliance & risk management' => getOilGasEnergyDocuments(),
            'Documentation & reporting' => getOilGasEnergyDocuments(),
            'On-site operation of oil & gas equipment' => getOilGasEnergyDocuments(),
            'Monitoring and maintenance' => getOilGasEnergyDocuments(),
            'Equipment troubleshooting' => getOilGasEnergyDocuments(),
            'Data collection & reporting' => getOilGasEnergyDocuments(),
            'Design, installation & maintenance of pipelines' => getOilGasEnergyDocuments(),
            'Pressure & flow analysis' => getOilGasEnergyDocuments(),
            'Project management & coordination' => getOilGasEnergyDocuments(),
            'Technical documentation & reporting' => getOilGasEnergyDocuments(),
            'Advisory on energy efficiency & optimization' => getOilGasEnergyDocuments(),
            'Data analysis & market research' => getOilGasEnergyDocuments(),
            'Regulatory & compliance guidance' => getOilGasEnergyDocuments(),
            'Project planning & recommendations' => getOilGasEnergyDocuments(),
            'Communication & stakeholder management' => getOilGasEnergyDocuments(),
            'Operation & maintenance of refinery equipment' => getOilGasEnergyDocuments(),
            'Monitoring process parameters & safety systems' => getOilGasEnergyDocuments(),
            'Compliance with safety & environmental standards' => getOilGasEnergyDocuments(),
            'Reporting & documentation' => getOilGasEnergyDocuments(),
            'Production optimization & monitoring' => getOilGasEnergyDocuments(),
            'Equipment & process troubleshooting' => getOilGasEnergyDocuments(),
            'Safety & compliance with operational standards' => getOilGasEnergyDocuments(),
            'Coordination with operations & maintenance teams' => getOilGasEnergyDocuments(),
            'Operation & maintenance of offshore rig equipment' => getOilGasEnergyDocuments(),
            'Safety compliance & emergency preparedness' => getOilGasEnergyDocuments(),
            'Monitoring & reporting operational parameters' => getOilGasEnergyDocuments(),
            'Team coordination & problem-solving' => getOilGasEnergyDocuments(),
            
            // Chemical / Industrial
            'Chemical process design & optimization' => getChemicalIndustrialDocuments(),
            'Equipment operation & troubleshooting' => getChemicalIndustrialDocuments(),
            'Safety & regulatory compliance (OSHA, HSE)' => getChemicalIndustrialDocuments(),
            'Process simulation & analysis' => getChemicalIndustrialDocuments(),
            'Project management & documentation' => getChemicalIndustrialDocuments(),
            'Sample preparation & chemical testing' => getChemicalIndustrialDocuments(),
            'Equipment operation & calibration' => getChemicalIndustrialDocuments(),
            'Data recording & analysis' => getChemicalIndustrialDocuments(),
            'Safety compliance & chemical handling' => getChemicalIndustrialDocuments(),
            'Reporting & documentation' => getChemicalIndustrialDocuments(),
            'Monitoring & controlling chemical processes' => getChemicalIndustrialDocuments(),
            'Equipment operation & adjustment' => getChemicalIndustrialDocuments(),
            'Safety & compliance with process standards' => getChemicalIndustrialDocuments(),
            'Troubleshooting & preventive maintenance' => getChemicalIndustrialDocuments(),
            'Data collection & reporting' => getChemicalIndustrialDocuments(),
            'Quality control testing & inspections' => getChemicalIndustrialDocuments(),
            'Data analysis & reporting' => getChemicalIndustrialDocuments(),
            'Compliance with industry standards & regulations' => getChemicalIndustrialDocuments(),
            'Problem-solving & process improvement' => getChemicalIndustrialDocuments(),
            'Documentation & record keeping' => getChemicalIndustrialDocuments(),
            'Formulation & chemical production' => getChemicalIndustrialDocuments(),
            'Process monitoring & optimization' => getChemicalIndustrialDocuments(),
            'Safety & compliance with chemical handling protocols' => getChemicalIndustrialDocuments(),
            'Maintenance & repair of industrial equipment' => getChemicalIndustrialDocuments(),
            'Equipment monitoring & troubleshooting' => getChemicalIndustrialDocuments(),
            'Safety & regulatory compliance' => getChemicalIndustrialDocuments(),
            'Process support & technical assistance' => getChemicalIndustrialDocuments(),
            'Record keeping & reporting' => getChemicalIndustrialDocuments(),
            'Documentation & reporting' => getChemicalIndustrialDocuments(),
            'Risk assessment & hazard identification' => getChemicalIndustrialDocuments(),
            'Safety protocol development & compliance' => getChemicalIndustrialDocuments(),
            'Emergency response planning' => getChemicalIndustrialDocuments(),
            'Training & communication' => getChemicalIndustrialDocuments(),
            'Development of chemical formulations' => getChemicalIndustrialDocuments(),
            'Laboratory testing & optimization' => getChemicalIndustrialDocuments(),
            'Knowledge of chemical properties & compatibility' => getChemicalIndustrialDocuments(),
            'Experimental design & chemical analysis' => getChemicalIndustrialDocuments(),
            'Laboratory techniques & instrumentation' => getChemicalIndustrialDocuments(),
            'Data analysis & interpretation' => getChemicalIndustrialDocuments(),
            'Scientific reporting & documentation' => getChemicalIndustrialDocuments(),
            'Monitoring & controlling industrial processes' => getChemicalIndustrialDocuments(),
            'Data recording & reporting' => getChemicalIndustrialDocuments(),
            'Problem-solving & communication' => getChemicalIndustrialDocuments(),
            'Chemical process monitoring & optimization' => getChemicalIndustrialDocuments(),
            'Laboratory testing & analysis' => getChemicalIndustrialDocuments(),
            'Equipment operation & safety compliance' => getChemicalIndustrialDocuments(),
            'Collaboration with production teams' => getChemicalIndustrialDocuments(),
            'Workplace safety management & inspections' => getChemicalIndustrialDocuments(),
            'Risk assessment & mitigation' => getChemicalIndustrialDocuments(),
            'Compliance with industrial regulations' => getChemicalIndustrialDocuments(),
            'Communication & training' => getChemicalIndustrialDocuments(),
            
            // Allied Health / Special Education / Therapy
            'Patient assessment & diagnosis' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Exercise prescription & rehabilitation planning' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Manual therapy & physical modalities' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient education & motivation' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Documentation & progress tracking' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Functional assessment & activity analysis' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Rehabilitation & adaptive technique planning' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient training in daily activities' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Communication & patient counseling' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Documentation & reporting' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Speech, language, & communication assessment' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Therapy planning & intervention' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Use of assistive technologies' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient & family education' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Documentation & progress reporting' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Individualized education plan (IEP) development' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Adapted teaching & learning strategies' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Behavior management & support' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Communication & collaboration with parents & staff' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Assessment & reporting' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient evaluation & goal setting' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Therapeutic intervention planning' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Multidisciplinary collaboration' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Monitoring & reporting progress' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient education & support' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Psychological assessment & testing' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Counseling & therapy techniques' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Behavioral analysis & intervention planning' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Research & data analysis' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Communication & documentation' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Hearing assessment & diagnosis' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Hearing aid fitting & management' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient counseling & education' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Use of audiology equipment & technology' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Design & fitting of orthotic devices' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient assessment & evaluation' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Equipment adjustment & troubleshooting' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Knowledge of anatomy & biomechanics' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Design & fitting of prosthetic devices' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Documentation & patient education' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Behavioral assessment & intervention planning' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Therapy implementation & monitoring' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient & family training' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Data collection & analysis' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Assisting therapists in rehabilitation sessions' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Equipment preparation & handling' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Patient support & monitoring' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Communication & teamwork' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Supporting students with learning difficulties' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Implementing individualized learning plans' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Collaboration with teachers & therapists' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Monitoring progress & reporting' => getAlliedHealthSpecialEducationTherapyDocuments(),
            'Communication & advocacy' => getAlliedHealthSpecialEducationTherapyDocuments(),
            
            // Sports / Fitness / Recreation
            'Exercise program design & instruction' => getSportsFitnessRecreationDocuments(),
            'Client assessment & fitness evaluation' => getSportsFitnessRecreationDocuments(),
            'Motivation & coaching skills' => getSportsFitnessRecreationDocuments(),
            'Knowledge of anatomy & physiology' => getSportsFitnessRecreationDocuments(),
            'Safety & injury prevention' => getSportsFitnessRecreationDocuments(),
            'Team management & leadership' => getSportsFitnessRecreationDocuments(),
            'Skill development & performance analysis' => getSportsFitnessRecreationDocuments(),
            'Motivation & communication' => getSportsFitnessRecreationDocuments(),
            'Strategic planning & game tactics' => getSportsFitnessRecreationDocuments(),
            'Assessment & feedback' => getSportsFitnessRecreationDocuments(),
            'Data collection & performance analysis' => getSportsFitnessRecreationDocuments(),
            'Statistical & analytical skills' => getSportsFitnessRecreationDocuments(),
            'Knowledge of sports rules & strategies' => getSportsFitnessRecreationDocuments(),
            'Reporting & presentation' => getSportsFitnessRecreationDocuments(),
            'Problem-solving & strategic insights' => getSportsFitnessRecreationDocuments(),
            'Planning & organizing recreational activities' => getSportsFitnessRecreationDocuments(),
            'Event coordination & scheduling' => getSportsFitnessRecreationDocuments(),
            'Communication & group facilitation' => getSportsFitnessRecreationDocuments(),
            'Safety & risk management' => getSportsFitnessRecreationDocuments(),
            'Budgeting & resource allocation' => getSportsFitnessRecreationDocuments(),
            'Fitness assessment & exercise guidance' => getSportsFitnessRecreationDocuments(),
            'Instruction on equipment use' => getSportsFitnessRecreationDocuments(),
            'Motivation & client engagement' => getSportsFitnessRecreationDocuments(),
            'Knowledge of anatomy & safety protocols' => getSportsFitnessRecreationDocuments(),
            'Monitoring & reporting client progress' => getSportsFitnessRecreationDocuments(),
            'Yoga practice & technique instruction' => getSportsFitnessRecreationDocuments(),
            'Client assessment & personalized guidance' => getSportsFitnessRecreationDocuments(),
            'Communication & motivation' => getSportsFitnessRecreationDocuments(),
            'Mindfulness & wellness coaching' => getSportsFitnessRecreationDocuments(),
            'Injury prevention & rehabilitation' => getSportsFitnessRecreationDocuments(),
            'Performance assessment & conditioning' => getSportsFitnessRecreationDocuments(),
            'Emergency response & first aid' => getSportsFitnessRecreationDocuments(),
            'Coaching & motivation' => getSportsFitnessRecreationDocuments(),
            'Record keeping & progress tracking' => getSportsFitnessRecreationDocuments(),
            'Knowledge of rules & regulations' => getSportsFitnessRecreationDocuments(),
            'Game monitoring & decision-making' => getSportsFitnessRecreationDocuments(),
            'Communication & conflict resolution' => getSportsFitnessRecreationDocuments(),
            'Observational & analytical skills' => getSportsFitnessRecreationDocuments(),
            'Reporting & record keeping' => getSportsFitnessRecreationDocuments(),
            'Water safety & rescue skills' => getSportsFitnessRecreationDocuments(),
            'First aid & CPR certification' => getSportsFitnessRecreationDocuments(),
            'Monitoring & surveillance' => getSportsFitnessRecreationDocuments(),
            'Emergency response & communication' => getSportsFitnessRecreationDocuments(),
            'Physical fitness & alertness' => getSportsFitnessRecreationDocuments(),
            'Lifestyle & wellness assessment' => getSportsFitnessRecreationDocuments(),
            'Health & fitness guidance' => getSportsFitnessRecreationDocuments(),
            'Motivation & behavior change strategies' => getSportsFitnessRecreationDocuments(),
            'Communication & counseling skills' => getSportsFitnessRecreationDocuments(),
            'Monitoring & progress tracking' => getSportsFitnessRecreationDocuments(),
            'Injury assessment & rehabilitation planning' => getSportsFitnessRecreationDocuments(),
            'Therapeutic exercise prescription' => getSportsFitnessRecreationDocuments(),
            'Manual therapy & modalities' => getSportsFitnessRecreationDocuments(),
            'Patient education & motivation' => getSportsFitnessRecreationDocuments(),
            'Documentation & progress tracking' => getSportsFitnessRecreationDocuments(),
            
            // Fashion / Apparel / Beauty
            'Clothing & accessory design' => getFashionApparelBeautyDocuments(),
            'Creativity & trend forecasting' => getFashionApparelBeautyDocuments(),
            'Pattern making & garment construction' => getFashionApparelBeautyDocuments(),
            'Technical drawing & CAD proficiency' => getFashionApparelBeautyDocuments(),
            'Fabric & material knowledge' => getFashionApparelBeautyDocuments(),
            'Personal styling & wardrobe selection' => getFashionApparelBeautyDocuments(),
            'Trend awareness & fashion knowledge' => getFashionApparelBeautyDocuments(),
            'Communication & client consultation' => getFashionApparelBeautyDocuments(),
            'Color coordination & outfit coordination' => getFashionApparelBeautyDocuments(),
            'Time management & organization' => getFashionApparelBeautyDocuments(),
            'Makeup application & technique' => getFashionApparelBeautyDocuments(),
            'Skin & cosmetic knowledge' => getFashionApparelBeautyDocuments(),
            'Creativity & aesthetic sense' => getFashionApparelBeautyDocuments(),
            'Client consultation & personalization' => getFashionApparelBeautyDocuments(),
            'Hygiene & safety compliance' => getFashionApparelBeautyDocuments(),
            'Retail management & merchandising' => getFashionApparelBeautyDocuments(),
            'Staff supervision & customer service' => getFashionApparelBeautyDocuments(),
            'Inventory management & ordering' => getFashionApparelBeautyDocuments(),
            'Sales & marketing strategies' => getFashionApparelBeautyDocuments(),
            'Financial & operational reporting' => getFashionApparelBeautyDocuments(),
            'Hair cutting, styling & coloring' => getFashionApparelBeautyDocuments(),
            'Knowledge of hair care & products' => getFashionApparelBeautyDocuments(),
            'Hygiene & safety standards' => getFashionApparelBeautyDocuments(),
            'Creativity & trend awareness' => getFashionApparelBeautyDocuments(),
            'Trend analysis & product selection' => getFashionApparelBeautyDocuments(),
            'Inventory planning & stock management' => getFashionApparelBeautyDocuments(),
            'Retail display & visual merchandising' => getFashionApparelBeautyDocuments(),
            'Sales & marketing coordination' => getFashionApparelBeautyDocuments(),
            'Data analysis & reporting' => getFashionApparelBeautyDocuments(),
            'Manicure & pedicure techniques' => getFashionApparelBeautyDocuments(),
            'Nail art & design' => getFashionApparelBeautyDocuments(),
            'Customer consultation & care' => getFashionApparelBeautyDocuments(),
            'Time management & precision' => getFashionApparelBeautyDocuments(),
            'Conceptual design for performances/productions' => getFashionApparelBeautyDocuments(),
            'Fabric selection & garment construction' => getFashionApparelBeautyDocuments(),
            'Collaboration with directors/stylists' => getFashionApparelBeautyDocuments(),
            'Creativity & technical drawing' => getFashionApparelBeautyDocuments(),
            'Project management & budgeting' => getFashionApparelBeautyDocuments(),
            'Personal wardrobe assessment' => getFashionApparelBeautyDocuments(),
            'Outfit coordination & styling advice' => getFashionApparelBeautyDocuments(),
            'Client communication & relationship management' => getFashionApparelBeautyDocuments(),
            'Organization & time management' => getFashionApparelBeautyDocuments(),
            'Skincare treatments & procedures' => getFashionApparelBeautyDocuments(),
            'Knowledge of beauty products & techniques' => getFashionApparelBeautyDocuments(),
            'Customer service & communication' => getFashionApparelBeautyDocuments(),
            'Artistic drawing & sketching skills' => getFashionApparelBeautyDocuments(),
            'Knowledge of fabrics & garment construction' => getFashionApparelBeautyDocuments(),
            'Creativity & conceptual design' => getFashionApparelBeautyDocuments(),
            'Digital illustration & CAD proficiency' => getFashionApparelBeautyDocuments(),
            'Attention to detail & presentation' => getFashionApparelBeautyDocuments(),
            'Personal branding & styling advice' => getFashionApparelBeautyDocuments(),
            'Wardrobe planning & color coordination' => getFashionApparelBeautyDocuments(),
            'Communication & client assessment' => getFashionApparelBeautyDocuments(),
            'Professional coaching & confidence building' => getFashionApparelBeautyDocuments(),
            
            // Home / Personal Services
            'Cleaning & sanitation of rooms and facilities' => getHomePersonalServicesDocuments(),
            'Organization & time management' => getHomePersonalServicesDocuments(),
            'Use of cleaning equipment & chemicals safely' => getHomePersonalServicesDocuments(),
            'Attention to detail' => getHomePersonalServicesDocuments(),
            'Customer service & discretion' => getHomePersonalServicesDocuments(),
            'Childcare & supervision' => getHomePersonalServicesDocuments(),
            'Meal preparation & feeding' => getHomePersonalServicesDocuments(),
            'Activity planning & educational support' => getHomePersonalServicesDocuments(),
            'Safety & emergency response' => getHomePersonalServicesDocuments(),
            'Communication with parents & reporting' => getHomePersonalServicesDocuments(),
            'Personal care assistance (bathing, grooming, feeding)' => getHomePersonalServicesDocuments(),
            'Monitoring health & medication adherence' => getHomePersonalServicesDocuments(),
            'Mobility support & safety supervision' => getHomePersonalServicesDocuments(),
            'Compassion & interpersonal communication' => getHomePersonalServicesDocuments(),
            'Documentation & reporting of care activities' => getHomePersonalServicesDocuments(),
            'Exercise program design & instruction' => getHomePersonalServicesDocuments(),
            'Fitness assessment & monitoring' => getHomePersonalServicesDocuments(),
            'Motivation & coaching skills' => getHomePersonalServicesDocuments(),
            'Knowledge of anatomy & physiology' => getHomePersonalServicesDocuments(),
            'Safety & injury prevention' => getHomePersonalServicesDocuments(),
            'Safe vehicle operation & navigation' => getHomePersonalServicesDocuments(),
            'Knowledge of traffic rules & regulations' => getHomePersonalServicesDocuments(),
            'Vehicle maintenance & inspection' => getHomePersonalServicesDocuments(),
            'Time management & punctuality' => getHomePersonalServicesDocuments(),
            'Communication & customer service' => getHomePersonalServicesDocuments(),
            'Plant care & landscaping' => getHomePersonalServicesDocuments(),
            'Pruning, planting, and maintenance' => getHomePersonalServicesDocuments(),
            'Use of gardening tools & equipment' => getHomePersonalServicesDocuments(),
            'Knowledge of soil, fertilizers, and irrigation' => getHomePersonalServicesDocuments(),
            'Safety & environmental awareness' => getHomePersonalServicesDocuments(),
            'Animal grooming techniques (bathing, trimming, styling)' => getHomePersonalServicesDocuments(),
            'Knowledge of animal behavior & safety' => getHomePersonalServicesDocuments(),
            'Customer communication & service' => getHomePersonalServicesDocuments(),
            'Equipment handling & maintenance' => getHomePersonalServicesDocuments(),
            'Attention to detail & hygiene' => getHomePersonalServicesDocuments(),
            'Washing, drying, and ironing clothes' => getHomePersonalServicesDocuments(),
            'Knowledge of fabrics & cleaning techniques' => getHomePersonalServicesDocuments(),
            'Organization & attention to detail' => getHomePersonalServicesDocuments(),
            'Customer service & efficiency' => getHomePersonalServicesDocuments(),
            'Scheduling & household management' => getHomePersonalServicesDocuments(),
            'Coordination of domestic staff & activities' => getHomePersonalServicesDocuments(),
            'Communication & task delegation' => getHomePersonalServicesDocuments(),
            'Budgeting & supply management' => getHomePersonalServicesDocuments(),
            'Discretion & confidentiality' => getHomePersonalServicesDocuments(),
            
            // Insurance / Risk / Banking
            'Client acquisition & relationship management' => getInsuranceRiskBankingDocuments(),
            'Knowledge of insurance products & policies' => getInsuranceRiskBankingDocuments(),
            'Risk assessment & coverage recommendation' => getInsuranceRiskBankingDocuments(),
            'Communication & negotiation skills' => getInsuranceRiskBankingDocuments(),
            'Documentation & compliance' => getInsuranceRiskBankingDocuments(),
            'Risk identification & assessment' => getInsuranceRiskBankingDocuments(),
            'Data analysis & modeling' => getInsuranceRiskBankingDocuments(),
            'Financial & market research' => getInsuranceRiskBankingDocuments(),
            'Reporting & documentation' => getInsuranceRiskBankingDocuments(),
            'Problem-solving & decision-making' => getInsuranceRiskBankingDocuments(),
            'Credit evaluation & loan processing' => getInsuranceRiskBankingDocuments(),
            'Customer consultation & assessment' => getInsuranceRiskBankingDocuments(),
            'Knowledge of banking & financial regulations' => getInsuranceRiskBankingDocuments(),
            'Documentation & reporting' => getInsuranceRiskBankingDocuments(),
            'Communication & problem-solving' => getInsuranceRiskBankingDocuments(),
            'Cash handling & transaction processing' => getInsuranceRiskBankingDocuments(),
            'Customer service & communication' => getInsuranceRiskBankingDocuments(),
            'Accuracy & attention to detail' => getInsuranceRiskBankingDocuments(),
            'Knowledge of banking procedures' => getInsuranceRiskBankingDocuments(),
            'Problem-solving & basic financial advising' => getInsuranceRiskBankingDocuments(),
            'Investigation & assessment of insurance claims' => getInsuranceRiskBankingDocuments(),
            'Client communication & negotiation' => getInsuranceRiskBankingDocuments(),
            'Knowledge of policy terms & legal requirements' => getInsuranceRiskBankingDocuments(),
            'Analytical & problem-solving skills' => getInsuranceRiskBankingDocuments(),
            'Risk assessment & evaluation' => getInsuranceRiskBankingDocuments(),
            'Policy review & approval' => getInsuranceRiskBankingDocuments(),
            'Financial analysis & decision-making' => getInsuranceRiskBankingDocuments(),
            'Compliance with regulations & standards' => getInsuranceRiskBankingDocuments(),
            'Financial planning & investment advice' => getInsuranceRiskBankingDocuments(),
            'Client relationship management' => getInsuranceRiskBankingDocuments(),
            'Knowledge of investment products & regulations' => getInsuranceRiskBankingDocuments(),
            'Credit evaluation & analysis' => getInsuranceRiskBankingDocuments(),
            'Financial statement interpretation' => getInsuranceRiskBankingDocuments(),
            'Risk assessment & reporting' => getInsuranceRiskBankingDocuments(),
            'Decision-making & recommendation' => getInsuranceRiskBankingDocuments(),
            'Communication & documentation' => getInsuranceRiskBankingDocuments(),
            'Portfolio management & investment strategy' => getInsuranceRiskBankingDocuments(),
            'Financial analysis & research' => getInsuranceRiskBankingDocuments(),
            'Client consultation & reporting' => getInsuranceRiskBankingDocuments(),
            'Market trend evaluation' => getInsuranceRiskBankingDocuments(),
            'Risk management & compliance' => getInsuranceRiskBankingDocuments(),
            'Assessment & recommendation of insurance policies' => getInsuranceRiskBankingDocuments(),
            'Client consultation & advisory' => getInsuranceRiskBankingDocuments(),
            'Knowledge of policy terms & regulations' => getInsuranceRiskBankingDocuments(),
            'Risk analysis & documentation' => getInsuranceRiskBankingDocuments(),
            'Communication & reporting' => getInsuranceRiskBankingDocuments(),
            'Customer service & relationship management' => getInsuranceRiskBankingDocuments(),
            'Banking operations & administration' => getInsuranceRiskBankingDocuments(),
            'Staff supervision & coordination' => getInsuranceRiskBankingDocuments(),
            'Compliance & regulatory knowledge' => getInsuranceRiskBankingDocuments(),
            'Problem-solving & reporting' => getInsuranceRiskBankingDocuments(),
            'Supporting underwriters in risk assessment' => getInsuranceRiskBankingDocuments(),
            'Data collection & documentation' => getInsuranceRiskBankingDocuments(),
            'Policy review & compliance support' => getInsuranceRiskBankingDocuments(),
            'Communication & coordination' => getInsuranceRiskBankingDocuments(),
            'Analytical & organizational skills' => getInsuranceRiskBankingDocuments(),
            
            // Micro Jobs / Informal / Daily Wage
            'Safe vehicle operation & navigation' => getMicroJobsInformalDailyWageDocuments(),
            'Time management & punctuality' => getMicroJobsInformalDailyWageDocuments(),
            'Route planning & traffic awareness' => getMicroJobsInformalDailyWageDocuments(),
            'Customer service & communication' => getMicroJobsInformalDailyWageDocuments(),
            'Vehicle maintenance & inspection' => getMicroJobsInformalDailyWageDocuments(),
            'Sales & customer service skills' => getMicroJobsInformalDailyWageDocuments(),
            'Product presentation & merchandising' => getMicroJobsInformalDailyWageDocuments(),
            'Cash handling & transaction management' => getMicroJobsInformalDailyWageDocuments(),
            'Inventory management & stock replenishment' => getMicroJobsInformalDailyWageDocuments(),
            'Communication & negotiation skills' => getMicroJobsInformalDailyWageDocuments(),
            'Physical stamina & endurance' => getMicroJobsInformalDailyWageDocuments(),
            'Knowledge of cleaning/construction tools & equipment' => getMicroJobsInformalDailyWageDocuments(),
            'Safety & hazard awareness' => getMicroJobsInformalDailyWageDocuments(),
            'Teamwork & coordination' => getMicroJobsInformalDailyWageDocuments(),
            'Time management & task completion' => getMicroJobsInformalDailyWageDocuments(),
            'Navigation & route planning' => getMicroJobsInformalDailyWageDocuments(),
            'Communication & reliability' => getMicroJobsInformalDailyWageDocuments(),
            'Task prioritization & organization' => getMicroJobsInformalDailyWageDocuments(),
            'Customer service & problem-solving' => getMicroJobsInformalDailyWageDocuments(),
            'Flexibility & adaptability' => getMicroJobsInformalDailyWageDocuments(),
            'Time management & self-motivation' => getMicroJobsInformalDailyWageDocuments(),
            'Task-specific skills depending on gig (e.g., delivery, labor, digital work)' => getMicroJobsInformalDailyWageDocuments(),
            'Problem-solving & reliability' => getMicroJobsInformalDailyWageDocuments(),
            
            // Real Estate / Property
            'Client relationship management' => getRealEstatePropertyDocuments(),
            'Property marketing & sales' => getRealEstatePropertyDocuments(),
            'Negotiation & closing deals' => getRealEstatePropertyDocuments(),
            'Market knowledge & property valuation' => getRealEstatePropertyDocuments(),
            'Communication & presentation skills' => getRealEstatePropertyDocuments(),
            'Property operations & maintenance oversight' => getRealEstatePropertyDocuments(),
            'Tenant relations & lease management' => getRealEstatePropertyDocuments(),
            'Budgeting & financial management' => getRealEstatePropertyDocuments(),
            'Vendor & contractor coordination' => getRealEstatePropertyDocuments(),
            'Regulatory compliance & reporting' => getRealEstatePropertyDocuments(),
            'Tenant acquisition & lease administration' => getRealEstatePropertyDocuments(),
            'Property showing & client consultation' => getRealEstatePropertyDocuments(),
            'Contract preparation & compliance' => getRealEstatePropertyDocuments(),
            'Communication & negotiation skills' => getRealEstatePropertyDocuments(),
            'Record keeping & reporting' => getRealEstatePropertyDocuments(),
            'Property valuation & market analysis' => getRealEstatePropertyDocuments(),
            'Data collection & research' => getRealEstatePropertyDocuments(),
            'Knowledge of real estate regulations & standards' => getRealEstatePropertyDocuments(),
            'Analytical & reporting skills' => getRealEstatePropertyDocuments(),
            'Attention to detail & accuracy' => getRealEstatePropertyDocuments(),
            'Market research & feasibility analysis' => getRealEstatePropertyDocuments(),
            'Client advisory & strategic planning' => getRealEstatePropertyDocuments(),
            'Project management & coordination' => getRealEstatePropertyDocuments(),
            'Financial analysis & investment evaluation' => getRealEstatePropertyDocuments(),
            
            // Entrepreneurship / Business / Corporate
            'Strategic planning & vision setting' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Leadership & team management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Decision-making & problem-solving' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Financial oversight & resource allocation' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Communication & stakeholder management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Business planning & strategy development' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Innovation & opportunity recognition' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Fundraising & investor relations' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Team leadership & resource management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Adaptability & problem-solving' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Data collection & business process analysis' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Problem-solving & decision support' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Reporting & documentation' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Process improvement & optimization' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Communication & stakeholder engagement' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Workflow & operational process management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Staff supervision & coordination' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Resource allocation & scheduling' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Quality assurance & efficiency monitoring' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Problem-solving & reporting' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Project planning & execution' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Resource & budget management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Risk assessment & mitigation' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Team coordination & communication' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Monitoring & reporting project progress' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Business process evaluation & optimization' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Strategic planning & advisory' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Data analysis & problem-solving' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Communication & client relationship management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Presentation & reporting' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Long-term business strategy development' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Market research & analysis' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Goal setting & performance metrics' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Stakeholder engagement & communication' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Problem-solving & decision-making' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Business growth strategy & planning' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Client relationship management' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Negotiation & deal-making' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Financial analysis & reporting' => getEntrepreneurshipBusinessCorporateDocuments(),
            'Team leadership & coordination' => getEntrepreneurshipBusinessCorporateDocuments(),
            
            // Technical Skills requiring proof
            'Typing Speed' => ['skills' => ['required' => false, 'label' => 'Proof of Typing/Computer Skills']],
            'Typing' => ['skills' => ['required' => false, 'label' => 'Proof of Typing/Computer Skills']],
            'Computer Skills' => ['skills' => ['required' => false, 'label' => 'Proof of Computer/Typing Skills']],
            'IT Skills' => ['skills' => ['required' => false, 'label' => 'Proof of IT/Technical Skills']],
            'Technical Knowledge' => ['skills' => ['required' => false, 'label' => 'Proof of Technical Background']],
            'Technical Skills' => ['skills' => ['required' => false, 'label' => 'Proof of Technical Skills']],
        ];
        
        // Define document requirements for each job title
        $requirements = [
            // Administrative / Office Jobs (requirements based on skills, not job title)
            'office administrator' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Executive Assistant / Administrative Coordinator
            'executive assistant' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'administrative coordinator' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Data Entry Clerk
            'data entry clerk' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Office Manager
            'office manager' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Receptionist
            'receptionist' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Personal Assistant
            'personal assistant' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Administrative Officer
            'administrative officer' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Records Clerk
            'records clerk' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Operations Assistant
            'operations assistant' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Secretary
            'secretary' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Front Desk Officer
            'front desk officer' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Executive Secretary
            'executive secretary' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Office Clerk
            'office clerk' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Filing Clerk
            'filing clerk' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Scheduling Coordinator
            'scheduling coordinator' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Office Services Manager
            'office services manager' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Documentation Specialist
            'documentation specialist' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Office Support Specialist
            'office support specialist' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Office Supervisor
            'office supervisor' => [
                'education' => ['required' => false, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Customer Service Representative
            'customer service representative' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Call Center Agent
            'call center agent' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Client Support Specialist
            'client support specialist' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Help Desk Associate
            'help desk associate' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Customer Care Coordinator
            'customer care coordinator' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Technical Support Representative
            'technical support representative' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Service Desk Analyst
            'service desk analyst' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Account Support Specialist
            'account support specialist' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Call Center Supervisor
            'call center supervisor' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Customer Experience Associate
            'customer experience associate' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Contact Center Trainer
            'contact center trainer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'skills' => ['required' => false, 'label' => 'Training / Certification Certificates (if available)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Chat Support Agent
            'chat support agent' => [
                'education' => ['required' => true, 'label' => 'Senior High School Certificate (College preferred)'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Email Support Specialist
            'email support specialist' => [
                'education' => ['required' => true, 'label' => 'Bachelor\'s Degree or proof of equivalent experience'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Escalation Officer
            'escalation officer' => [
                'education' => ['required' => false, 'label' => 'Bachelor\'s Degree (preferred)'],
                'work_experience' => ['required' => true, 'label' => 'Proof of experience handling escalated customer issues'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // QA Analyst (Customer Service)
            'qa analyst' => [
                'education' => ['required' => true, 'label' => 'Bachelor\'s Degree or proof of equivalent experience'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Customer Retention Specialist
            'customer retention specialist' => [
                'education' => ['required' => true, 'label' => 'Bachelor\'s Degree or proof of equivalent experience'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Virtual Customer Service Associate
            'virtual customer service associate' => [
                'education' => ['required' => true, 'label' => 'Bachelor\'s Degree or proof of relevant experience'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Inside Sales / Customer Support
            'inside sales' => [
                'education' => ['required' => true, 'label' => 'Bachelor\'s Degree or proof of equivalent experience'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Team Lead – Customer Support
            'team lead' => [
                'education' => ['required' => false, 'label' => 'Bachelor\'s Degree (preferred)'],
                'work_experience' => ['required' => true, 'label' => 'Proof of experience leading a customer service team'],
                'id_document' => ['required' => true, 'label' => 'Valid IDs'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter'],
                'nbi_clearance' => ['required' => false]
            ],
            // Teacher
            'teacher' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // School Counselor
            'school counselor' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Academic Coordinator
            'academic coordinator' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Tutor
            'tutor' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Principal
            'principal' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Librarian
            'librarian' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Special Education Teacher
            'special education teacher' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Curriculum Developer
            'curriculum developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Education Program Manager
            'education program manager' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Lecturer
            'lecturer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // College Instructor
            'college instructor' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Preschool Teacher
            'preschool teacher' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Teaching Assistant
            'teaching assistant' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Instructional Designer
            'instructional designer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Learning Facilitator
            'learning facilitator' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Education Consultant
            'education consultant' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Homeroom Teacher
            'homeroom teacher' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // School Administrator
            'school administrator' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Guidance Counselor
            'guidance counselor' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Academic Adviser
            'academic adviser' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Teaching License / Professional License (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Engineering Jobs
            'civil engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'mechanical engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'electrical engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'project engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'structural engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'chemical engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'industrial engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'process engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'quality engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'design engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'maintenance engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'field engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'systems engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'engineering technician' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'automation engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'product design engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'control systems engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'environmental engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'safety engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'reliability engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional License / Certification (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            // Information Technology (IT) Jobs
            'software developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'network administrator' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'it support specialist' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'web developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'systems analyst' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'database administrator' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'cybersecurity analyst' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'cloud engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'it manager' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'technical lead' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'application developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'devops engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'mobile app developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'data engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'network security engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'it project manager' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'ux/ui developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'front-end developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'back-end developer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'it infrastructure engineer' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'it consultant' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ],
            'it auditor' => [
                'education' => ['required' => true, 'label' => 'Diploma / Transcript of Records'],
                'work_experience' => ['required' => false, 'label' => 'Certificate of Employment / Experience Letter (if applicable)'],
                'skills' => ['required' => false, 'label' => 'Professional Certifications (if applicable)'],
                'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter (optional)'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio / Work Samples (if applicable)'],
                'nbi_clearance' => ['required' => false]
            ]
        ];
        
        // Check for exact or partial match in job title
        foreach ($requirements as $key => $req) {
            if (strpos($title_lower, $key) !== false) {
                // If job requirements field contains relevant keywords, adjust document requirements
                $adjusted_req = $req;
                
                // First, check if the selected required skill matches any skill-to-document mapping
                if (!empty($requirements_value) && isset($skillToDocuments[$requirements_value])) {
                    $skill_docs = $skillToDocuments[$requirements_value];
                    foreach ($skill_docs as $doc_key => $doc_req) {
                        // If document already exists, merge but prioritize skill-based requirement
                        if (isset($adjusted_req[$doc_key])) {
                            // If skill requires it, make it required
                            if ($doc_req['required'] === true) {
                                $adjusted_req[$doc_key] = $doc_req;
                            }
                        } else {
                            $adjusted_req[$doc_key] = $doc_req;
                        }
                    }
                }
                
                // Also check if requirements mention PRC License, certification, etc. (fallback)
                if (!empty($requirements_lower)) {
                    // If requirements mention PRC, license, or certification, make skills field more relevant
                    if (strpos($requirements_lower, 'prc') !== false || 
                        strpos($requirements_lower, 'license') !== false || 
                        strpos($requirements_lower, 'certification') !== false) {
                        // Ensure skills field exists and is relevant
                        if (!isset($adjusted_req['skills'])) {
                            $adjusted_req['skills'] = ['required' => false, 'label' => 'PRC License / Certification (if required)'];
                        }
                    }
                    
                    // If requirements mention experience, ensure work_experience is required
                    if ((strpos($requirements_lower, 'experience') !== false || 
                         strpos($requirements_lower, 'years') !== false) && 
                        !isset($adjusted_req['work_experience'])) {
                        $adjusted_req['work_experience'] = ['required' => true, 'label' => 'Proof of relevant work experience'];
                    }
                    
                    // If requirements mention degree, education, bachelor, master, ensure education is required
                    if ((strpos($requirements_lower, 'degree') !== false || 
                         strpos($requirements_lower, 'bachelor') !== false ||
                         strpos($requirements_lower, 'master') !== false ||
                         strpos($requirements_lower, 'education') !== false) && 
                        !isset($adjusted_req['education'])) {
                        $adjusted_req['education'] = ['required' => true, 'label' => 'Educational Certificate / Transcript'];
                    }
                }
                
                return $adjusted_req;
            }
        }
        
        // Check if requirements field contains a skill that maps to documents
        if (!empty($requirements_value) && isset($skillToDocuments[$requirements_value])) {
            $skill_docs = $skillToDocuments[$requirements_value];
            $default_req = [
                'id_document' => ['required' => true, 'label' => 'Valid IDs & Clearances'],
                'cover_letter' => ['required' => false, 'label' => 'Cover Letter']
            ];
            
            // Merge skill-based requirements
            foreach ($skill_docs as $doc_key => $doc_req) {
                $default_req[$doc_key] = $doc_req;
            }
            
            // Add education if not present but skill requires it
            if (!isset($default_req['education']) && 
                (strpos($requirements_lower, 'degree') !== false || 
                 strpos($requirements_lower, 'bachelor') !== false ||
                 strpos($requirements_lower, 'education') !== false)) {
                $default_req['education'] = ['required' => true, 'label' => 'Educational Certificate / Transcript'];
            }
            
            return $default_req;
        }
        
        // Default requirements (Office Administrator)
        return [
            'education' => ['required' => true, 'label' => 'Bachelor\'s Degree Diploma & Transcript of Records (TOR)'],
            'id_document' => ['required' => true, 'label' => 'Government-issued ID'],
            'nbi_clearance' => ['required' => true, 'label' => 'NBI Clearance or Police Clearance'],
            'work_experience' => ['required' => true, 'label' => 'Certificate(s) of Employment from previous employers'],
            'skills' => ['required' => false, 'label' => 'Training or MS Office Certificates'],
            'reference_letters' => ['required' => false, 'label' => 'Reference Letters'],
            'portfolio' => ['required' => false, 'label' => 'Portfolio or Work Samples'],
            'medical_certificate' => ['required' => false, 'label' => 'Medical Certificate (if requested)'],
            'barangay_clearance' => ['required' => false, 'label' => 'Barangay Clearance or Community Tax Certificate (Cedula) (if requested)'],
            'cover_letter' => ['required' => false, 'label' => 'Cover Letter']
        ];
    }
}

// Clean up expired jobs with no applications
cleanupExpiredJobs();

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    redirect('jobs.php');
}

// Get job details with company information - exclude expired jobs (deadline passed)
$stmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, c.description as company_description, 
                              c.location_address, c.contact_email, c.status as company_status,
                              jc.category_name,
                              (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as total_applications
                       FROM job_postings jp
                       JOIN companies c ON jp.company_id = c.id
                       LEFT JOIN job_categories jc ON jp.category_id = jc.id
                       WHERE jp.id = ? AND jp.status = 'active' AND c.status = 'active' 
                       AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    redirect('jobs.php');
}

$employees_required_max = getEmployeesRequiredMax($job['employees_required'] ?? '');
$job_is_full = $employees_required_max !== null && (int)$job['total_applications'] >= $employees_required_max;

if ($job_is_full && ($job['status'] ?? '') === 'active') {
    $expireStmt = $pdo->prepare("UPDATE job_postings SET status = 'expired', updated_at = NOW() WHERE id = ? AND status = 'active'");
    $expireStmt->execute([$job_id]);
}

// Check if user is logged in and get their profile
$is_employee = false;
$employee_profile = null;
$already_applied = false;
$is_saved = false;
$can_apply = false;
$skills_match = false;
$experience_match = false;
$company_verified = ($job['company_status'] ?? '') === 'active';

if (isLoggedIn() && getUserRole() === 'employee') {
    $is_employee = true;
    
    // Get employee profile
    $stmt = $pdo->prepare("SELECT * FROM employee_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $employee_profile = $stmt->fetch();
    
    if ($employee_profile) {
        // Check if already applied and get application details
        $stmt = $pdo->prepare("SELECT id, status, interview_status FROM job_applications WHERE employee_id = ? AND job_id = ?");
        $stmt->execute([$employee_profile['id'], $job_id]);
        $application_info = $stmt->fetch();
        $already_applied = $application_info !== false;
        $application_status = $already_applied ? $application_info['status'] : null;
        $application_interview_status = $already_applied ? $application_info['interview_status'] : null;
        
        // Check if job is saved
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
        $stmt->execute([$employee_profile['id'], $job_id]);
        $is_saved = $stmt->fetchColumn() > 0;
    }

    if ($employee_profile) {
        $skills_match = hasRequiredSkills($job['requirements'] ?? '', $employee_profile['skills'] ?? '');
        $experience_match = computeExperienceMatch($job['experience_level'] ?? '', $employee_profile['experience_level'] ?? '');
        $can_apply = $company_verified && $skills_match && $experience_match && !$job_is_full;
    }
}

// Handle job application
$application_message = '';
$application_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_job'])) {
    // Get document requirements based on job title and required skills
    $doc_requirements = getJobDocumentRequirements($job['title'], $job['requirements'] ?? '');
    $is_simplified_docs = !($doc_requirements['nbi_clearance']['required'] ?? false);
    
    if (!$is_employee) {
        $application_error = 'You must be logged in as an employee to apply for jobs.';
    } elseif (!$employee_profile) {
        $application_error = 'Please complete your profile before applying for jobs.';
    } elseif ($already_applied) {
        $application_error = 'You have already applied for this job.';
    } elseif (!$company_verified) {
        $application_error = 'You can only apply to verified companies.';
    } elseif ($job_is_full) {
        $application_error = 'This job is no longer accepting applications.';
    } elseif (!$can_apply) {
        $application_error = 'You can only apply if your skills and experience match this job.';
    } else {
        // Handle cover letter upload if provided
        $cover_letter_path = '';
        if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['cover_letter']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['cover_letter']['size'] > $max_file_size) {
                $application_error = 'Cover letter file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                // Verify it's actually an image file
                $image_info = getimagesize($_FILES['cover_letter']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'cover_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $cover_letter_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['cover_letter']['tmp_name'], $cover_letter_path)) {
                        $cover_letter_path = '';
                        $application_error = 'Failed to upload cover letter. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid cover letter image file.';
                }
            } else {
                $application_error = 'Please upload a valid cover letter (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['cover_letter']['required'] ?? false)) {
            $application_error = 'Cover Letter is required.';
        }
        
        // Handle Bachelor's Diploma & TOR upload (required)
        $bachelor_diploma_path = '';
        if (!$application_error && isset($_FILES['bachelor_diploma']) && $_FILES['bachelor_diploma']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['bachelor_diploma']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['bachelor_diploma']['size'] > $max_file_size) {
                $application_error = 'Bachelor\'s Diploma & TOR file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['bachelor_diploma']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'diploma_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $bachelor_diploma_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['bachelor_diploma']['tmp_name'], $bachelor_diploma_path)) {
                        $bachelor_diploma_path = '';
                        $application_error = 'Failed to upload Bachelor\'s Diploma & TOR. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Bachelor\'s Diploma & TOR image file.';
                }
            } else {
                $application_error = 'Please upload a valid Bachelor\'s Diploma & TOR (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['education']['required'] ?? false)) {
            $education_label = $doc_requirements['education']['label'] ?? 'Educational Certificate';
            $application_error = $education_label . ' is required.';
        }
        
        // Handle Certificate of Enrollment upload
        $certificate_of_enrollment_path = '';
        if (!$application_error && isset($_FILES['certificate_of_enrollment']) && $_FILES['certificate_of_enrollment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['certificate_of_enrollment']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['certificate_of_enrollment']['size'] > $max_file_size) {
                $application_error = 'Certificate of Enrollment file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['certificate_of_enrollment']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'enrollment_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $certificate_of_enrollment_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['certificate_of_enrollment']['tmp_name'], $certificate_of_enrollment_path)) {
                        $certificate_of_enrollment_path = '';
                        $application_error = 'Failed to upload Certificate of Enrollment. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Certificate of Enrollment image file.';
                }
            } else {
                $application_error = 'Please upload a valid Certificate of Enrollment (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['certificate_of_enrollment']['required'] ?? false)) {
            $application_error = 'Certificate of Enrollment is required.';
        }
        
        // Handle Application Letter upload
        $application_letter_path = '';
        if (!$application_error && isset($_FILES['application_letter']) && $_FILES['application_letter']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['application_letter']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['application_letter']['size'] > $max_file_size) {
                $application_error = 'Application Letter file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['application_letter']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'appletter_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $application_letter_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['application_letter']['tmp_name'], $application_letter_path)) {
                        $application_letter_path = '';
                        $application_error = 'Failed to upload Application Letter. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Application Letter image file.';
                }
            } else {
                $application_error = 'Please upload a valid Application Letter (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['application_letter']['required'] ?? false)) {
            $application_error = 'Application Letter is required.';
        }
        
        // Handle Certificate of Graduation upload
        $certificate_of_graduation_path = '';
        if (!$application_error && isset($_FILES['certificate_of_graduation']) && $_FILES['certificate_of_graduation']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['certificate_of_graduation']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['certificate_of_graduation']['size'] > $max_file_size) {
                $application_error = 'Certificate of Graduation file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['certificate_of_graduation']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'graduation_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $certificate_of_graduation_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['certificate_of_graduation']['tmp_name'], $certificate_of_graduation_path)) {
                        $certificate_of_graduation_path = '';
                        $application_error = 'Failed to upload Certificate of Graduation. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Certificate of Graduation image file.';
                }
            } else {
                $application_error = 'Please upload a valid Certificate of Graduation (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['certificate_of_graduation']['required'] ?? false)) {
            $application_error = 'Certificate of Graduation is required.';
        }
        
        // Handle Professional License upload
        $professional_license_path = '';
        if (!$application_error && isset($_FILES['professional_license']) && $_FILES['professional_license']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['professional_license']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['professional_license']['size'] > $max_file_size) {
                $application_error = 'Professional License file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['professional_license']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'license_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $professional_license_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['professional_license']['tmp_name'], $professional_license_path)) {
                        $professional_license_path = '';
                        $application_error = 'Failed to upload Professional License. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Professional License image file.';
                }
            } else {
                $application_error = 'Please upload a valid Professional License (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['professional_license']['required'] ?? false)) {
            $application_error = 'Professional License is required.';
        }
        
        // Handle Certificate of Eligibility upload
        $certificate_of_eligibility_path = '';
        if (!$application_error && isset($_FILES['certificate_of_eligibility']) && $_FILES['certificate_of_eligibility']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['certificate_of_eligibility']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['certificate_of_eligibility']['size'] > $max_file_size) {
                $application_error = 'Certificate of Eligibility file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['certificate_of_eligibility']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'eligibility_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $certificate_of_eligibility_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['certificate_of_eligibility']['tmp_name'], $certificate_of_eligibility_path)) {
                        $certificate_of_eligibility_path = '';
                        $application_error = 'Failed to upload Certificate of Eligibility. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Certificate of Eligibility image file.';
                }
            } else {
                $application_error = 'Please upload a valid Certificate of Eligibility (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['certificate_of_eligibility']['required'] ?? false)) {
            $application_error = 'Certificate of Eligibility is required.';
        }
        
        // Handle TESDA NC Certificate upload
        $tesda_nc_certificate_path = '';
        if (!$application_error && isset($_FILES['tesda_nc_certificate']) && $_FILES['tesda_nc_certificate']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['tesda_nc_certificate']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['tesda_nc_certificate']['size'] > $max_file_size) {
                $application_error = 'TESDA NC Certificate file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['tesda_nc_certificate']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'tesda_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $tesda_nc_certificate_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['tesda_nc_certificate']['tmp_name'], $tesda_nc_certificate_path)) {
                        $tesda_nc_certificate_path = '';
                        $application_error = 'Failed to upload TESDA NC Certificate. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid TESDA NC Certificate image file.';
                }
            } else {
                $application_error = 'Please upload a valid TESDA NC Certificate (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['tesda_nc_certificate']['required'] ?? false)) {
            $application_error = 'TESDA NC Certificate is required.';
        }
        
        // Handle Physical Fitness Certificate upload
        $physical_fitness_certificate_path = '';
        if (!$application_error && isset($_FILES['physical_fitness_certificate']) && $_FILES['physical_fitness_certificate']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['physical_fitness_certificate']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['physical_fitness_certificate']['size'] > $max_file_size) {
                $application_error = 'Physical Fitness Certificate file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['physical_fitness_certificate']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'fitness_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $physical_fitness_certificate_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['physical_fitness_certificate']['tmp_name'], $physical_fitness_certificate_path)) {
                        $physical_fitness_certificate_path = '';
                        $application_error = 'Failed to upload Physical Fitness Certificate. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Physical Fitness Certificate image file.';
                }
            } else {
                $application_error = 'Please upload a valid Physical Fitness Certificate (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['physical_fitness_certificate']['required'] ?? false)) {
            $application_error = 'Physical Fitness Certificate is required.';
        }
        
        // Handle Drug Test Result upload
        $drug_test_result_path = '';
        if (!$application_error && isset($_FILES['drug_test_result']) && $_FILES['drug_test_result']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['drug_test_result']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['drug_test_result']['size'] > $max_file_size) {
                $application_error = 'Drug Test Result file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['drug_test_result']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'drugtest_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $drug_test_result_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['drug_test_result']['tmp_name'], $drug_test_result_path)) {
                        $drug_test_result_path = '';
                        $application_error = 'Failed to upload Drug Test Result. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Drug Test Result image file.';
                }
            } else {
                $application_error = 'Please upload a valid Drug Test Result (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['drug_test_result']['required'] ?? false)) {
            $application_error = 'Drug Test Result is required.';
        }
        
        // Handle Psychological Examination Result upload
        $psychological_examination_result_path = '';
        if (!$application_error && isset($_FILES['psychological_examination_result']) && $_FILES['psychological_examination_result']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['psychological_examination_result']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['psychological_examination_result']['size'] > $max_file_size) {
                $application_error = 'Psychological Examination Result file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['psychological_examination_result']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'psycho_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $psychological_examination_result_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['psychological_examination_result']['tmp_name'], $psychological_examination_result_path)) {
                        $psychological_examination_result_path = '';
                        $application_error = 'Failed to upload Psychological Examination Result. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Psychological Examination Result image file.';
                }
            } else {
                $application_error = 'Please upload a valid Psychological Examination Result (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['psychological_examination_result']['required'] ?? false)) {
            $application_error = 'Psychological Examination Result is required.';
        }
        
        // Handle Teaching Demonstration Plan / Lesson Plan upload
        $teaching_demonstration_plan_path = '';
        if (!$application_error && isset($_FILES['teaching_demonstration_plan']) && $_FILES['teaching_demonstration_plan']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['teaching_demonstration_plan']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['teaching_demonstration_plan']['size'] > $max_file_size) {
                $application_error = 'Teaching Demonstration Plan / Lesson Plan file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['teaching_demonstration_plan']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'lessonplan_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $teaching_demonstration_plan_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['teaching_demonstration_plan']['tmp_name'], $teaching_demonstration_plan_path)) {
                        $teaching_demonstration_plan_path = '';
                        $application_error = 'Failed to upload Teaching Demonstration Plan / Lesson Plan. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Teaching Demonstration Plan / Lesson Plan image file.';
                }
            } else {
                $application_error = 'Please upload a valid Teaching Demonstration Plan / Lesson Plan (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['teaching_demonstration_plan']['required'] ?? false)) {
            $application_error = 'Teaching Demonstration Plan / Lesson Plan is required.';
        }
        
        // Handle Teaching Portfolio upload
        $teaching_portfolio_path = '';
        if (!$application_error && isset($_FILES['teaching_portfolio']) && $_FILES['teaching_portfolio']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['teaching_portfolio']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['teaching_portfolio']['size'] > $max_file_size) {
                $application_error = 'Teaching Portfolio file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['teaching_portfolio']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'teachportfolio_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $teaching_portfolio_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['teaching_portfolio']['tmp_name'], $teaching_portfolio_path)) {
                        $teaching_portfolio_path = '';
                        $application_error = 'Failed to upload Teaching Portfolio. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Teaching Portfolio image file.';
                }
            } else {
                $application_error = 'Please upload a valid Teaching Portfolio (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['teaching_portfolio']['required'] ?? false)) {
            $application_error = 'Teaching Portfolio is required.';
        }
        
        // Handle ID document upload (required)
        $id_document_path = '';
        if (!$application_error && isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['id_document']['size'] > $max_file_size) {
                $application_error = 'ID document file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                // Verify it's actually an image file
                $image_info = getimagesize($_FILES['id_document']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'id_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $id_document_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['id_document']['tmp_name'], $id_document_path)) {
                        $id_document_path = '';
                        $application_error = 'Failed to upload ID document. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid ID document image file.';
                }
            } else {
                $application_error = 'Please upload a valid ID document (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error) {
            $application_error = 'Valid IDs is required.';
        }
        
        // Handle NBI Clearance upload
        $nbi_clearance_path = '';
        if (!$application_error && isset($_FILES['nbi_clearance']) && $_FILES['nbi_clearance']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['nbi_clearance']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['nbi_clearance']['size'] > $max_file_size) {
                $application_error = 'NBI Clearance file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['nbi_clearance']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'nbi_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $nbi_clearance_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['nbi_clearance']['tmp_name'], $nbi_clearance_path)) {
                        $nbi_clearance_path = '';
                        $application_error = 'Failed to upload NBI Clearance. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid NBI Clearance image file.';
                }
            } else {
                $application_error = 'Please upload a valid NBI Clearance (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['nbi_clearance']['required'] ?? false)) {
            $application_error = 'NBI Clearance is required.';
        }
        
        // Handle Employment Certificate upload (required)
        $employment_certificate_path = '';
        if (!$application_error && isset($_FILES['employment_certificate']) && $_FILES['employment_certificate']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['employment_certificate']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['employment_certificate']['size'] > $max_file_size) {
                $application_error = 'Employment Certificate file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['employment_certificate']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'empcert_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $employment_certificate_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['employment_certificate']['tmp_name'], $employment_certificate_path)) {
                        $employment_certificate_path = '';
                        $application_error = 'Failed to upload Employment Certificate. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Employment Certificate image file.';
                }
            } else {
                $application_error = 'Please upload a valid Employment Certificate (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['work_experience']['required'] ?? false)) {
            $work_exp_label = $doc_requirements['work_experience']['label'] ?? 'Proof of Work Experience';
            $application_error = $work_exp_label . ' is required.';
        }
        
        // Handle Training / MS Office Certificates upload
        $training_certificates_path = '';
        if (!$application_error && isset($_FILES['training_certificates']) && $_FILES['training_certificates']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['training_certificates']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['training_certificates']['size'] > $max_file_size) {
                $application_error = 'Training / MS Office Certificates file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['training_certificates']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'training_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $training_certificates_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['training_certificates']['tmp_name'], $training_certificates_path)) {
                        $training_certificates_path = '';
                        $application_error = 'Failed to upload Training / MS Office Certificates. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Training / MS Office Certificates image file.';
                }
            } else {
                $application_error = 'Please upload a valid Training / MS Office Certificates (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['skills']['required'] ?? false)) {
            $skills_label = $doc_requirements['skills']['label'] ?? 'Certificates / Trainings';
            $application_error = $skills_label . ' is required.';
        }
        
        // Handle Reference Letters / Recommendation Letters upload
        $reference_letters_path = '';
        if (!$application_error && isset($_FILES['reference_letters']) && $_FILES['reference_letters']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['reference_letters']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['reference_letters']['size'] > $max_file_size) {
                $application_error = 'Reference Letters file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['reference_letters']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'reference_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $reference_letters_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['reference_letters']['tmp_name'], $reference_letters_path)) {
                        $reference_letters_path = '';
                        $application_error = 'Failed to upload Reference Letters. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Reference Letters image file.';
                }
            } else {
                $application_error = 'Please upload a valid Reference Letters (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['reference_letters']['required'] ?? false)) {
            $ref_label = $doc_requirements['reference_letters']['label'] ?? 'Professional References';
            $application_error = $ref_label . ' is required.';
        }
        
        // Handle Recommendation Letters upload (alias for reference_letters for Education)
        if (!$application_error && isset($_FILES['recommendation_letters']) && $_FILES['recommendation_letters']['error'] === UPLOAD_ERR_OK) {
            if (empty($reference_letters_path)) {
                $upload_dir = 'uploads/resumes/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['recommendation_letters']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB limit
                
                if ($_FILES['recommendation_letters']['size'] > $max_file_size) {
                    $application_error = 'Recommendation Letters file size must be less than 5MB.';
                } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $image_info = getimagesize($_FILES['recommendation_letters']['tmp_name']);
                    if ($image_info !== false) {
                        $filename = 'recommendation_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                        $reference_letters_path = $upload_dir . $filename;
                        
                        if (!move_uploaded_file($_FILES['recommendation_letters']['tmp_name'], $reference_letters_path)) {
                            $reference_letters_path = '';
                            $application_error = 'Failed to upload Recommendation Letters. Please try again.';
                        }
                    } else {
                        $application_error = 'Please upload a valid Recommendation Letters image file.';
                    }
                } else {
                    $application_error = 'Please upload a valid Recommendation Letters (JPG, JPEG, PNG, GIF, BMP, WEBP).';
                }
            }
        } elseif (!$application_error && ($doc_requirements['recommendation_letters']['required'] ?? false)) {
            if (empty($reference_letters_path)) {
                $application_error = 'Recommendation Letters (School Head / Supervisor) is required.';
            }
        }
        
        // Handle Portfolio or Work Samples upload
        $portfolio_path = '';
        if (!$application_error && isset($_FILES['portfolio']) && $_FILES['portfolio']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['portfolio']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['portfolio']['size'] > $max_file_size) {
                $application_error = 'Portfolio or Work Samples file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['portfolio']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'portfolio_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $portfolio_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['portfolio']['tmp_name'], $portfolio_path)) {
                        $portfolio_path = '';
                        $application_error = 'Failed to upload Portfolio or Work Samples. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Portfolio or Work Samples image file.';
                }
            } else {
                $application_error = 'Please upload a valid Portfolio or Work Samples (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['portfolio']['required'] ?? false)) {
            $portfolio_label = $doc_requirements['portfolio']['label'] ?? 'Portfolio / Work Samples';
            $application_error = $portfolio_label . ' is required.';
        }
        
        // Handle Medical Certificate upload
        $medical_certificate_path = '';
        if (!$application_error && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['medical_certificate']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['medical_certificate']['size'] > $max_file_size) {
                $application_error = 'Medical Certificate file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['medical_certificate']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'medical_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $medical_certificate_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $medical_certificate_path)) {
                        $medical_certificate_path = '';
                        $application_error = 'Failed to upload Medical Certificate. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Medical Certificate image file.';
                }
            } else {
                $application_error = 'Please upload a valid Medical Certificate (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['medical_certificate']['required'] ?? false)) {
            $application_error = 'Medical Certificate is required.';
        }
        
        // Handle Barangay Clearance upload
        $barangay_clearance_path = '';
        if (!$application_error && isset($_FILES['barangay_clearance']) && $_FILES['barangay_clearance']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['barangay_clearance']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['barangay_clearance']['size'] > $max_file_size) {
                $application_error = 'Barangay Clearance file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['barangay_clearance']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'barangay_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $barangay_clearance_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['barangay_clearance']['tmp_name'], $barangay_clearance_path)) {
                        $barangay_clearance_path = '';
                        $application_error = 'Failed to upload Barangay Clearance. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Barangay Clearance image file.';
                }
            } else {
                $application_error = 'Please upload a valid Barangay Clearance (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['barangay_clearance']['required'] ?? false)) {
            $application_error = 'Barangay Clearance is required.';
        }
        
        // Handle Personal Data Sheet upload
        $personal_data_sheet_path = '';
        if (!$application_error && isset($_FILES['personal_data_sheet']) && $_FILES['personal_data_sheet']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['personal_data_sheet']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['personal_data_sheet']['size'] > $max_file_size) {
                $application_error = 'Personal Data Sheet file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['personal_data_sheet']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'pds_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $personal_data_sheet_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['personal_data_sheet']['tmp_name'], $personal_data_sheet_path)) {
                        $personal_data_sheet_path = '';
                        $application_error = 'Failed to upload Personal Data Sheet. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Personal Data Sheet image file.';
                }
            } else {
                $application_error = 'Please upload a valid Personal Data Sheet (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['personal_data_sheet']['required'] ?? false)) {
            $application_error = 'Personal Data Sheet is required.';
        }
        
        // Handle Birth Certificate upload
        $birth_certificate_path = '';
        if (!$application_error && isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['birth_certificate']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['birth_certificate']['size'] > $max_file_size) {
                $application_error = 'Birth Certificate file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['birth_certificate']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'birth_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $birth_certificate_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['birth_certificate']['tmp_name'], $birth_certificate_path)) {
                        $birth_certificate_path = '';
                        $application_error = 'Failed to upload Birth Certificate. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Birth Certificate image file.';
                }
            } else {
                $application_error = 'Please upload a valid Birth Certificate (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['birth_certificate']['required'] ?? false)) {
            $application_error = 'Birth Certificate is required.';
        }
        
        // Handle Police Clearance upload
        $police_clearance_path = '';
        if (!$application_error && isset($_FILES['police_clearance']) && $_FILES['police_clearance']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['police_clearance']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['police_clearance']['size'] > $max_file_size) {
                $application_error = 'Police Clearance file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['police_clearance']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'police_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $police_clearance_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['police_clearance']['tmp_name'], $police_clearance_path)) {
                        $police_clearance_path = '';
                        $application_error = 'Failed to upload Police Clearance. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Police Clearance image file.';
                }
            } else {
                $application_error = 'Please upload a valid Police Clearance (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['police_clearance']['required'] ?? false)) {
            $application_error = 'Police Clearance is required.';
        }
        
        // Handle Diploma upload (separate from TOR)
        $diploma_path = '';
        if (!$application_error && isset($_FILES['diploma']) && $_FILES['diploma']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['diploma']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['diploma']['size'] > $max_file_size) {
                $application_error = 'Diploma file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['diploma']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'diploma_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $diploma_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['diploma']['tmp_name'], $diploma_path)) {
                        $diploma_path = '';
                        $application_error = 'Failed to upload Diploma. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Diploma image file.';
                }
            } else {
                $application_error = 'Please upload a valid Diploma (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['diploma']['required'] ?? false)) {
            $application_error = 'Diploma is required.';
        }
        
        // Handle 2x2 ID Photo upload
        $id_photo_path = '';
        if (!$application_error && isset($_FILES['id_photo']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['id_photo']['size'] > $max_file_size) {
                $application_error = '2x2 ID Photo file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['id_photo']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'idphoto_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $id_photo_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['id_photo']['tmp_name'], $id_photo_path)) {
                        $id_photo_path = '';
                        $application_error = 'Failed to upload 2x2 ID Photo. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid 2x2 ID Photo image file.';
                }
            } else {
                $application_error = 'Please upload a valid 2x2 ID Photo (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['id_photo']['required'] ?? false)) {
            $application_error = '2x2 ID Photo is required.';
        }
        
        // Handle Certificate of Good Moral Character upload
        $good_moral_character_path = '';
        if (!$application_error && isset($_FILES['good_moral_character']) && $_FILES['good_moral_character']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['good_moral_character']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB limit
            
            if ($_FILES['good_moral_character']['size'] > $max_file_size) {
                $application_error = 'Certificate of Good Moral Character file size must be less than 5MB.';
            } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
                $image_info = getimagesize($_FILES['good_moral_character']['tmp_name']);
                if ($image_info !== false) {
                    $filename = 'goodmoral_' . $employee_profile['id'] . '_' . time() . '.' . $file_extension;
                    $good_moral_character_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['good_moral_character']['tmp_name'], $good_moral_character_path)) {
                        $good_moral_character_path = '';
                        $application_error = 'Failed to upload Certificate of Good Moral Character. Please try again.';
                    }
                } else {
                    $application_error = 'Please upload a valid Certificate of Good Moral Character image file.';
                }
            } else {
                $application_error = 'Please upload a valid Certificate of Good Moral Character (JPG, JPEG, PNG, GIF, BMP, WEBP).';
            }
        } elseif (!$application_error && ($doc_requirements['good_moral_character']['required'] ?? false)) {
            $application_error = 'Certificate of Good Moral Character is required.';
        }
        
        // Final validation: Check all required documents are uploaded
        if (!$application_error) {
            $missing_docs = [];
            $field_mapping = [
                'application_letter' => ['path' => $application_letter_path ?? '', 'label' => $doc_requirements['application_letter']['label'] ?? 'Application Letter'],
                'cover_letter' => ['path' => $cover_letter_path, 'label' => $doc_requirements['cover_letter']['label'] ?? 'Cover Letter'],
                'personal_data_sheet' => ['path' => $personal_data_sheet_path ?? '', 'label' => $doc_requirements['personal_data_sheet']['label'] ?? 'Personal Data Sheet'],
                'birth_certificate' => ['path' => $birth_certificate_path ?? '', 'label' => $doc_requirements['birth_certificate']['label'] ?? 'Birth Certificate'],
                'barangay_clearance' => ['path' => $barangay_clearance_path, 'label' => $doc_requirements['barangay_clearance']['label'] ?? 'Barangay Clearance'],
                'police_clearance' => ['path' => $police_clearance_path ?? '', 'label' => $doc_requirements['police_clearance']['label'] ?? 'Police Clearance'],
                'nbi_clearance' => ['path' => $nbi_clearance_path, 'label' => 'NBI Clearance'],
                'medical_certificate' => ['path' => $medical_certificate_path, 'label' => $doc_requirements['medical_certificate']['label'] ?? 'Medical Certificate'],
                'education' => ['path' => $bachelor_diploma_path, 'label' => $doc_requirements['education']['label'] ?? 'Educational Certificate'],
                'certificate_of_enrollment' => ['path' => $certificate_of_enrollment_path ?? '', 'label' => $doc_requirements['certificate_of_enrollment']['label'] ?? 'Certificate of Enrollment'],
                'diploma' => ['path' => $diploma_path ?? '', 'label' => $doc_requirements['diploma']['label'] ?? 'Diploma'],
                'certificate_of_graduation' => ['path' => $certificate_of_graduation_path ?? '', 'label' => $doc_requirements['certificate_of_graduation']['label'] ?? 'Certificate of Graduation'],
                'professional_license' => ['path' => $professional_license_path ?? '', 'label' => $doc_requirements['professional_license']['label'] ?? 'Professional License'],
                'certificate_of_eligibility' => ['path' => $certificate_of_eligibility_path ?? '', 'label' => $doc_requirements['certificate_of_eligibility']['label'] ?? 'Certificate of Eligibility'],
                'work_experience' => ['path' => $employment_certificate_path, 'label' => $doc_requirements['work_experience']['label'] ?? 'Work Experience'],
                'skills' => ['path' => $training_certificates_path, 'label' => $doc_requirements['skills']['label'] ?? 'Certificates/Trainings'],
                'tesda_nc_certificate' => ['path' => $tesda_nc_certificate_path ?? '', 'label' => $doc_requirements['tesda_nc_certificate']['label'] ?? 'TESDA NC Certificate'],
                'physical_fitness_certificate' => ['path' => $physical_fitness_certificate_path ?? '', 'label' => $doc_requirements['physical_fitness_certificate']['label'] ?? 'Physical Fitness Certificate'],
                'drug_test_result' => ['path' => $drug_test_result_path ?? '', 'label' => $doc_requirements['drug_test_result']['label'] ?? 'Drug Test Result'],
                'psychological_examination_result' => ['path' => $psychological_examination_result_path ?? '', 'label' => $doc_requirements['psychological_examination_result']['label'] ?? 'Psychological Examination Result'],
                'teaching_demonstration_plan' => ['path' => $teaching_demonstration_plan_path ?? '', 'label' => $doc_requirements['teaching_demonstration_plan']['label'] ?? 'Teaching Demonstration Plan / Lesson Plan'],
                'teaching_portfolio' => ['path' => $teaching_portfolio_path ?? '', 'label' => $doc_requirements['teaching_portfolio']['label'] ?? 'Teaching Portfolio'],
                'id_document' => ['path' => $id_document_path, 'label' => 'Valid IDs'],
                'id_photo' => ['path' => $id_photo_path ?? '', 'label' => $doc_requirements['id_photo']['label'] ?? '2x2 ID Photo'],
                'good_moral_character' => ['path' => $good_moral_character_path ?? '', 'label' => $doc_requirements['good_moral_character']['label'] ?? 'Certificate of Good Moral Character'],
                'reference_letters' => ['path' => $reference_letters_path, 'label' => $doc_requirements['reference_letters']['label'] ?? 'Professional References'],
                'recommendation_letters' => ['path' => $reference_letters_path, 'label' => $doc_requirements['recommendation_letters']['label'] ?? 'Recommendation Letters'],
                'portfolio' => ['path' => $portfolio_path, 'label' => $doc_requirements['portfolio']['label'] ?? 'Portfolio/Work Samples']
            ];
            
            foreach ($doc_requirements as $key => $req) {
                if (($req['required'] ?? false) && isset($field_mapping[$key])) {
                    $field = $field_mapping[$key];
                    if (empty($field['path'])) {
                        $missing_docs[] = $field['label'];
                    }
                }
            }
            
            if (!empty($missing_docs)) {
                $application_error = 'Please upload all required documents: ' . implode(', ', $missing_docs);
            }
        }
        
        // Insert application
        // Note: Using existing columns for backward compatibility:
        // - tor_document: stores bachelor_diploma & TOR
        // - seminar_certificate: stores training_certificates  
        // - certificate_of_attachment: stores reference_letters
        // - certificate_of_reports: stores portfolio
        // - certificate_of_good_standing: stores medical_certificate
        // - nbi_clearance: new column (run add_application_document_columns.sql)
        // - barangay_clearance: new column (run add_application_document_columns.sql)
        try {
            // Try to insert with all columns including nbi_clearance and barangay_clearance
            $stmt = $pdo->prepare("INSERT INTO job_applications (employee_id, job_id, cover_letter, id_document, tor_document, employment_certificate, nbi_clearance, seminar_certificate, certificate_of_attachment, certificate_of_reports, certificate_of_good_standing, barangay_clearance, applied_date, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
            
            $insert_success = false;
            if (!$application_error) {
                $insert_success = $stmt->execute([
                    $employee_profile['id'], 
                    $job_id, 
                    $cover_letter_path, 
                    $id_document_path, 
                    $bachelor_diploma_path, 
                    $employment_certificate_path,
                    $nbi_clearance_path,
                    $training_certificates_path,
                    $reference_letters_path,
                    $portfolio_path,
                    $medical_certificate_path,
                    $barangay_clearance_path
                ]);
                
                // Mark as fast application if user has premium subscription
                if ($insert_success && hasActiveSubscription($_SESSION['user_id'])) {
                    $application_id = $pdo->lastInsertId();
                    markFastApplication($application_id);
                }
            }
        } catch (PDOException $e) {
            // If columns don't exist, try without nbi_clearance and barangay_clearance
            if (strpos($e->getMessage(), 'nbi_clearance') !== false || strpos($e->getMessage(), 'barangay_clearance') !== false) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO job_applications (employee_id, job_id, cover_letter, id_document, tor_document, employment_certificate, seminar_certificate, certificate_of_attachment, certificate_of_reports, certificate_of_good_standing, applied_date, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
                    $insert_success = $stmt->execute([
                        $employee_profile['id'], 
                        $job_id, 
                        $cover_letter_path, 
                        $id_document_path, 
                        $bachelor_diploma_path, 
                        $employment_certificate_path,
                        $training_certificates_path,
                        $reference_letters_path,
                        $portfolio_path,
                        $medical_certificate_path
                    ]);
                    
                    // Try to update nbi_clearance and barangay_clearance if columns exist
                    if ($insert_success) {
                        $application_id = $pdo->lastInsertId();
                        try {
                            $updateStmt = $pdo->prepare("UPDATE job_applications SET nbi_clearance = ? WHERE id = ?");
                            $updateStmt->execute([$nbi_clearance_path, $application_id]);
                        } catch (PDOException $e2) {
                            // Column doesn't exist yet
                        }
                        try {
                            $updateStmt = $pdo->prepare("UPDATE job_applications SET barangay_clearance = ? WHERE id = ?");
                            $updateStmt->execute([$barangay_clearance_path, $application_id]);
                        } catch (PDOException $e2) {
                            // Column doesn't exist yet
                        }
                        
                        // Mark as fast application if user has premium subscription
                        if (hasActiveSubscription($_SESSION['user_id'])) {
                            markFastApplication($application_id);
                        }
                    }
                } catch (PDOException $e2) {
                    $insert_success = false;
                    if (!$application_error) {
                        $application_error = 'Failed to submit application. Please run sql/add_application_document_columns.sql to add required columns.';
                    }
                }
            } else {
                $insert_success = false;
                if (!$application_error) {
                    $application_error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        if (!$application_error && $insert_success) {
            $application_message = 'Your application has been submitted successfully!';
            $already_applied = true;
            $job['total_applications'] = (int)$job['total_applications'] + 1;

            if ($employees_required_max !== null && $job['total_applications'] >= $employees_required_max) {
                $expireStmt = $pdo->prepare("UPDATE job_postings SET status = 'expired', updated_at = NOW() WHERE id = ? AND status = 'active'");
                $expireStmt->execute([$job_id]);
                $job_is_full = true;
                $can_apply = false;
            }
        } elseif (!$application_error) {
            $application_error = 'Failed to submit application. Please try again.';
        }
    }
}

// Handle save/unsave job
if (isset($_POST['save_job']) || isset($_POST['unsave_job'])) {
    if ($is_employee && $employee_profile) {
        if (isset($_POST['save_job'])) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO saved_jobs (employee_id, job_id, saved_date) VALUES (?, ?, NOW())");
            $stmt->execute([$employee_profile['id'], $job_id]);
            $is_saved = true;
        } else {
            $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE employee_id = ? AND job_id = ?");
            $stmt->execute([$employee_profile['id'], $job_id]);
            $is_saved = false;
        }
    }
}

// Get similar jobs - exclude expired jobs (deadline passed)
$stmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo 
                       FROM job_postings jp
                       JOIN companies c ON jp.company_id = c.id
                       WHERE jp.id != ? AND jp.status = 'active' 
                       AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())
                       AND (jp.category_id = ? OR jp.location LIKE ?)
                       ORDER BY jp.posted_date DESC
                       LIMIT 4");
$stmt->execute([$job_id, $job['category_id'], '%' . $job['location'] . '%']);
$similar_jobs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['company_name']); ?> | WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        :root {
            --brand: #3b82f6;
            --brand-dark: #1d4ed8;
            --brand-strong: #2563eb;
            --warning: #f59e0b;
            --ink: #0f172a;
            --muted: #64748b;
            --surface: #ffffff;
            --page-bg: #f4f7fb;
        }

        body.job-page {
            background: var(--page-bg);
            color: var(--ink);
        }

        .job-nav {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            box-shadow: 0 10px 30px rgba(29, 78, 216, 0.25);
        }

        .job-content {
            margin-top: 96px;
        }

        .job-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .job-card::before {
            content: "";
            display: block;
            height: 6px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
        }

        .job-card .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .text-accent {
            color: var(--brand) !important;
        }

        .text-muted-strong {
            color: var(--muted);
        }

        .badge-accent {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: #fff;
            border-radius: 999px;
            padding: 0.45rem 0.75rem;
        }

        .badge-soft {
            background: rgba(59, 130, 246, 0.12);
            color: var(--brand-strong);
            border-radius: 999px;
            padding: 0.45rem 0.75rem;
        }

        .btn-accent {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .btn-accent:hover {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
            color: #fff;
        }

        .btn-accent-outline {
            border-color: var(--brand);
            color: var(--brand);
            background: transparent;
        }

        .btn-accent-outline:hover {
            background: var(--brand);
            color: #fff;
        }

        .btn-warn {
            background: var(--warning);
            border-color: var(--warning);
            color: #fff;
        }

        .btn-warn:hover {
            background: #d97706;
            border-color: #d97706;
            color: #fff;
        }

        .btn-warn-outline {
            border-color: var(--warning);
            color: var(--warning);
            background: transparent;
        }

        .btn-warn-outline:hover {
            background: var(--warning);
            color: #fff;
        }

        .section-title {
            font-weight: 700;
            color: var(--ink);
        }

        .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .meta-icon {
            color: var(--brand);
        }
    </style>
</head>
<body class="job-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top job-nav">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="worklink.jpg" alt="WORKLINK" class="logo-img me-2" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover;">
                WORKLINK
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="companies.php">Companies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="jobs.php">Available Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php $role = getUserRole(); ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo ucfirst($role); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $role; ?>/dashboard.php">Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-auth-login px-3" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-auth-signup ms-2 px-3" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5 job-content">
        <!-- Back to Jobs -->
        <div class="mb-3">
            <a href="jobs.php" class="btn btn-accent-outline">
                <i class="fas fa-arrow-left me-1"></i>Back to Jobs
            </a>
        </div>

        <div class="row">
            <!-- Job Details -->
            <div class="col-lg-8">
                <div class="card job-card">
                    <div class="card-body">
                        <!-- Job Header -->
                        <div class="d-flex align-items-start mb-4">
                            <?php if ($job['company_logo']): ?>
                                <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                     alt="Company Logo" class="me-3" 
                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <h1 class="h3 mb-2"><?php echo htmlspecialchars($job['title']); ?></h1>
                                <h2 class="h5 text-accent mb-3"><?php echo htmlspecialchars($job['company_name']); ?></h2>
                                
                                <div class="row text-muted-strong small">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <i class="fas fa-map-marker-alt me-2 meta-icon"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <i class="fas fa-briefcase me-2 meta-icon"></i>
                                            <?php echo htmlspecialchars($job['employment_type'] ?? ''); ?>
                                        </div>
                                        <?php if (!empty($job['job_type'])): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-calendar-check me-2 meta-icon"></i>
                                            <?php echo htmlspecialchars($job['job_type']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mb-2">
                                            <i class="fas fa-users me-2 meta-icon"></i>
                                            <?php echo $job['employees_required']; ?> Employee<?php echo $job['employees_required'] === '1' ? '' : 's'; ?> Required
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($job['salary_range']): ?>
                                            <div class="mb-2">
                                                <i class="fas fa-peso-sign me-2 meta-icon"></i>
                                                <?php echo htmlspecialchars($job['salary_range']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-2">
                                            <i class="fas fa-clock me-2 meta-icon"></i>
                                            Posted <?php echo timeAgo($job['posted_date']); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Job Meta -->
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <?php if ($job['category_name']): ?>
                                        <span class="badge badge-accent"><?php echo htmlspecialchars($job['category_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($job['deadline']): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-calendar me-1"></i>
                                            Deadline: <?php echo date('M j, Y', strtotime($job['deadline'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge badge-soft"><?php echo htmlspecialchars($job['experience_level']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Application Status Messages -->
                        <?php if ($application_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $application_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($application_error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $application_error; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Job Description -->
                        <h4 class="section-title">Job Description</h4>
                        <div class="mb-4">
                            <?php echo nl2br(htmlspecialchars(html_entity_decode($job['description'], ENT_QUOTES, 'UTF-8'))); ?>
                        </div>

                        <!-- Requirements -->
                        <?php if ($job['requirements']): ?>
                            <h4 class="section-title">Skills Match</h4>
                            <div class="mb-4">
                                <?php echo nl2br(htmlspecialchars(html_entity_decode($job['requirements'], ENT_QUOTES, 'UTF-8'))); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Qualifications -->
                        <?php if (!empty($job['qualification'])): ?>
                            <h4 class="section-title">Qualifications</h4>
                            <div class="mb-4">
                                <?php echo nl2br(html_entity_decode($job['qualification'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Additional Job Info -->
                        <div class="row">
                        </div>
                    </div>
                </div>

                <!-- Application Form -->
                <?php if ($is_employee && $employee_profile && !$already_applied && $can_apply): 
                    // Get document requirements based on job title and requirements
                    $doc_requirements = getJobDocumentRequirements($job['title'], $job['requirements'] ?? '');
                ?>
                    <div class="card job-card mt-4" id="apply">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Apply for this Job</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Show required documents notice
                            $required_docs = [];
                            foreach ($doc_requirements as $key => $req) {
                                if ($req['required'] ?? false) {
                                    $required_docs[] = $req['label'] ?? ucfirst(str_replace('_', ' ', $key));
                                }
                            }
                            if (!empty($required_docs)): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Required Documents:</strong> Please ensure you have all the following documents ready before applying:
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($required_docs as $doc): ?>
                                            <li><?php echo htmlspecialchars($doc); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <small class="d-block mt-2">You will not be able to submit your application without all required documents.</small>
                                </div>
                            <?php endif; ?>
                            <form method="POST" enctype="multipart/form-data">
                                <?php
                                // Map document keys to form field names
                                $field_mapping = [
                                    'application_letter' => 'application_letter',
                                    'cover_letter' => 'cover_letter',
                                    'personal_data_sheet' => 'personal_data_sheet',
                                    'birth_certificate' => 'birth_certificate',
                                    'barangay_clearance' => 'barangay_clearance',
                                    'police_clearance' => 'police_clearance',
                                    'nbi_clearance' => 'nbi_clearance',
                                    'medical_certificate' => 'medical_certificate',
                                    'education' => 'bachelor_diploma',
                                    'certificate_of_enrollment' => 'certificate_of_enrollment',
                                    'diploma' => 'diploma',
                                    'certificate_of_graduation' => 'certificate_of_graduation',
                                    'professional_license' => 'professional_license',
                                    'certificate_of_eligibility' => 'certificate_of_eligibility',
                                    'work_experience' => 'employment_certificate',
                                    'skills' => 'training_certificates',
                                    'tesda_nc_certificate' => 'tesda_nc_certificate',
                                    'physical_fitness_certificate' => 'physical_fitness_certificate',
                                    'drug_test_result' => 'drug_test_result',
                                    'psychological_examination_result' => 'psychological_examination_result',
                                    'teaching_demonstration_plan' => 'teaching_demonstration_plan',
                                    'teaching_portfolio' => 'teaching_portfolio',
                                    'id_document' => 'id_document',
                                    'id_photo' => 'id_photo',
                                    'good_moral_character' => 'good_moral_character',
                                    'reference_letters' => 'reference_letters',
                                    'recommendation_letters' => 'recommendation_letters',
                                    'portfolio' => 'portfolio'
                                ];
                                
                                // Group fields into rows of 2
                                $fields_to_show = [];
                                foreach ($doc_requirements as $key => $req) {
                                    // Show all fields that are required, or show optional fields if they exist in mapping
                                    if (($req['required'] ?? false) || isset($field_mapping[$key])) {
                                        $fields_to_show[] = [
                                            'key' => $key,
                                            'field_name' => $field_mapping[$key] ?? $key,
                                            'label' => $req['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                                            'required' => $req['required'] ?? false
                                        ];
                                    }
                                }
                                
                                // Display fields in rows of 2
                                for ($i = 0; $i < count($fields_to_show); $i += 2):
                                    $field1 = $fields_to_show[$i];
                                    $field2 = isset($fields_to_show[$i + 1]) ? $fields_to_show[$i + 1] : null;
                                ?>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="<?php echo htmlspecialchars($field1['field_name']); ?>" class="form-label">
                                            <?php echo htmlspecialchars($field1['label']); ?>
                                            <?php if ($field1['required']): ?><span class="text-danger">*</span><?php endif; ?>
                                        </label>
                                        <input type="file" class="form-control" 
                                               id="<?php echo htmlspecialchars($field1['field_name']); ?>" 
                                               name="<?php echo htmlspecialchars($field1['field_name']); ?>" 
                                               accept="image/*" 
                                               <?php echo $field1['required'] ? 'required' : ''; ?>>
                                        <div class="form-text">Upload as an image - Max size: 5MB</div>
                                    </div>
                                    <?php if ($field2): ?>
                                    <div class="col-md-6 mb-3">
                                        <label for="<?php echo htmlspecialchars($field2['field_name']); ?>" class="form-label">
                                            <?php echo htmlspecialchars($field2['label']); ?>
                                            <?php if ($field2['required']): ?><span class="text-danger">*</span><?php endif; ?>
                                        </label>
                                        <input type="file" class="form-control" 
                                               id="<?php echo htmlspecialchars($field2['field_name']); ?>" 
                                               name="<?php echo htmlspecialchars($field2['field_name']); ?>" 
                                               accept="image/*" 
                                               <?php echo $field2['required'] ? 'required' : ''; ?>>
                                        <div class="form-text">Upload as an image - Max size: 5MB</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endfor; ?>
                                <button type="submit" name="apply_job" class="btn btn-accent">
                                    <i class="fas fa-paper-plane me-1"></i>Submit Application
                                </button>
                            </form>
                        </div>
                    </div>
                <?php elseif ($already_applied): ?>
                    <div class="card job-card mt-4">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle mb-3" style="color: #2563eb; font-size: 3rem;"></i>
                            <h5>Application Submitted</h5>
                            <?php if ($application_status): ?>
                                <div class="mb-2">
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch($application_status) {
                                        case 'pending':
                                            $statusClass = 'bg-warning text-dark';
                                            $statusIcon = 'clock';
                                            break;
                                        case 'reviewed':
                                            $statusClass = 'bg-info text-white';
                                            $statusIcon = 'eye';
                                            break;
                                        case 'accepted':
                                            $statusClass = 'bg-success text-white';
                                            $statusIcon = 'check';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-danger text-white';
                                            $statusIcon = 'times';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> fs-6 mb-2">
                                        <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                        Status: <?php echo ucfirst($application_status); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($application_interview_status): ?>
                                <div class="mb-2">
                                    <?php
                                    $interviewStatusClass = $application_interview_status === 'interviewed' ? 'bg-success text-white' : 'bg-secondary text-white';
                                    $interviewStatusIcon = $application_interview_status === 'interviewed' ? 'check-circle' : 'calendar-times';
                                    ?>
                                    <span class="badge <?php echo $interviewStatusClass; ?> fs-6 mb-2">
                                        <i class="fas fa-<?php echo $interviewStatusIcon; ?> me-1"></i>
                                        Interview: <?php echo ucfirst($application_interview_status); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <p class="text-secondary">You have already applied for this position. Check your application status in your dashboard.</p>
                            <a href="employee/applications.php" class="btn btn-accent-outline">
                                View Applications
                            </a>
                        </div>
                    </div>
                <?php elseif ($job_is_full): ?>
                    <div class="card job-card mt-4">
                        <div class="card-body text-center">
                            <i class="fas fa-user-check fa-3x text-secondary mb-3"></i>
                            <h5>Position Filled</h5>
                            <p class="text-secondary">This job is no longer accepting applications.</p>
                        </div>
                    </div>
                <?php elseif ($is_employee && $employee_profile && !$can_apply): ?>
                    <div class="card job-card mt-4">
                        <div class="card-body text-center">
                            <i class="fas fa-user-check fa-3x text-secondary mb-3"></i>
                            <h5>Do not match</h5>
                            <p class="text-secondary">You can only apply if your skills and experience match this job.</p>
                            <div class="small text-secondary mb-3">
                                <div>Skills match: <?php echo $skills_match ? 'Yes' : 'No'; ?></div>
                                <div>Experience match: <?php echo $experience_match ? 'Yes' : 'No'; ?></div>
                            </div>
                        </div>
                    </div>
                <?php elseif (!$is_employee): ?>
                    <div class="card job-card mt-4">
                        <div class="card-body text-center">
                            <i class="fas fa-user-plus fa-3x text-secondary mb-3"></i>
                            <h5>Ready to Apply?</h5>
                            <p class="text-secondary">Create an employee account to apply for this job and track your applications.</p>
                            <a href="register.php" class="btn btn-accent me-2">Register as Employee</a>
                            <a href="login.php" class="btn btn-accent-outline">Login</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Action Buttons -->
                <?php if ($is_employee && $employee_profile): ?>
                    <div class="card job-card mb-4">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if (!$already_applied): ?>
                                    <?php if ($job_is_full): ?>
                                        <button class="btn btn-secondary" type="button" disabled>
                                            <i class="fas fa-ban me-1"></i>Position Filled
                                        </button>
                                    <?php elseif ($can_apply): ?>
                                        <a href="#apply" class="btn btn-accent">
                                            <i class="fas fa-paper-plane me-1"></i>Apply Now
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" type="button" disabled>
                                            <i class="fas fa-lock me-1"></i>Not Eligible
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <form method="POST" class="d-inline">
                                    <?php if (!$is_saved): ?>
                                        <button type="submit" name="save_job" class="btn btn-warn-outline w-100">
                                            <i class="fas fa-heart me-1"></i>Save Job
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="unsave_job" class="btn btn-warn w-100">
                                            <i class="fas fa-heart me-1"></i>Saved
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Company Info -->
                <div class="card job-card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">About <?php echo htmlspecialchars($job['company_name']); ?></h6>
                    </div>
                    <div class="card-body">
                        <?php if ($job['company_description']): ?>
                            <p class="small"><?php echo nl2br(htmlspecialchars(substr($job['company_description'], 0, 200))); ?><?php echo strlen($job['company_description']) > 200 ? '...' : ''; ?></p>
                        <?php endif; ?>
                        
                        <div class="small">
                            
                            <?php if ($job['location_address']): ?>
                                <div class="mb-2">
                                    <strong>Address:</strong> <?php echo htmlspecialchars($job['location_address']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="companies.php?search=<?php echo urlencode($job['company_name']); ?>" class="btn btn-sm btn-accent-outline">
                            View Company Profile
                        </a>
                    </div>
                </div>

                <!-- Job Stats -->
                <div class="card job-card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Job Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h4 class="stat-number text-accent"><?php echo $job['total_applications']; ?></h4>
                                    <small class="text-secondary">Applications</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h4 class="stat-number text-accent"><?php echo $job['views'] ?? 0; ?></h4>
                                <small class="text-secondary">Views</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Similar Jobs -->
                <?php if (!empty($similar_jobs)): ?>
                    <div class="card job-card">
                        <div class="card-header">
                            <h6 class="mb-0">Similar Jobs</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($similar_jobs as $similar): ?>
                                <div class="p-3 border-bottom">
                                    <h6 class="mb-1">
                                        <a href="job-details.php?id=<?php echo $similar['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($similar['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="text-accent small mb-1"><?php echo htmlspecialchars($similar['company_name']); ?></p>
                                    <p class="text-secondary small mb-0">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($similar['location']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer text-center">
                            <a href="jobs.php" class="btn btn-sm btn-accent-outline">View All Jobs</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>WORKLINK</h5>
                    <p class="small">Connecting talent with opportunity.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small">&copy; 2025 WORKLINK. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
