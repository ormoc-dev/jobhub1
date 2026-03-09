<?php
include '../config.php';
requireRole('employer');

$message = '';
$error = '';

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Please complete your company profile first.';
    header('Location: company-profile.php');
    exit;
}

// Get job categories
$categories = $pdo->query("SELECT * FROM job_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();

$categoryNameById = [];
$categoryIdByName = [];
foreach ($categories as $category) {
    $categoryNameById[$category['id']] = $category['category_name'];
    $categoryIdByName[$category['category_name']] = $category['id'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $requirementsInput = $_POST['requirements'] ?? '';
    if (is_array($requirementsInput)) {
        $requirements = implode(',', array_map('sanitizeInput', $requirementsInput));
    } else {
        $requirements = sanitizeInput($requirementsInput);
    }
    // Get category ID from category name or direct ID
    $categoryNameOrId = $_POST['category_id'] ?? '';
    if (is_numeric($categoryNameOrId)) {
        $categoryId = (int)$categoryNameOrId;
    } else {
        $categoryId = $categoryIdByName[$categoryNameOrId] ?? 0;
    }
    $location = sanitizeInput($_POST['location']);
    $salaryRange = sanitizeInput($_POST['salary_range']);
    $jobType = sanitizeInput($_POST['job_type']);
    $experienceLevel = sanitizeInput($_POST['experience_level']);
    $educationRequirement = sanitizeInput($_POST['education_requirement']);
    $qualification = sanitizeInput($_POST['qualification'] ?? '');
    $courses = sanitizeInput($_POST['courses'] ?? '');
    $employeesRequired = sanitizeInput($_POST['employees_required']);
    $deadline = !empty($_POST['deadline']) ? sanitizeInput($_POST['deadline']) : null;
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($requirements) || empty($location) || empty($jobType) || empty($experienceLevel) || empty($salaryRange) || empty($employeesRequired)) {
        $error = 'Please fill in all required fields.';
    } elseif ($title === 'College Instructor' && empty($courses)) {
        $error = 'Please select a course to teach for College Instructor position.';
    } else {
        try {
            // Note: courses column is defined in sql/all_additional_tables.sql
            // Set status to 'pending' so admin needs to approve before it goes live
            $stmt = $pdo->prepare("INSERT INTO job_postings (company_id, title, description, requirements, category_id, location, salary_range, job_type, experience_level, education_requirement, qualification, courses, employees_required, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$company['id'], $title, $description, $requirements, $categoryId ?: null, $location, $salaryRange, $jobType, $experienceLevel, $educationRequirement, $qualification, $courses ?: null, $employeesRequired, $deadline]);
            
            $message = 'Job posted successfully! It will be reviewed by admin before going live.';
            
            // Clear form data
            $_POST = array();
            
        } catch (Exception $e) {
            $error = 'Error posting job: ' . $e->getMessage();
        }
    }
}

$locationValue = isset($_POST['location']) ? $_POST['location'] : ($company['location_address'] ?? '');

// Parse location value to extract city and barangay codes for pre-selection
$jobCityCode = '';
$jobBarangayCode = '';

// Try to parse existing location value (format: "Barangay, City" or "Barangay, City, Province, Region")
if (!empty($locationValue)) {
    $locationParts = array_map('trim', explode(',', $locationValue));
    // We'll match by name using the PSGC API in JavaScript
}
$salaryRangeValue = isset($_POST['salary_range']) ? $_POST['salary_range'] : 'Negotiable';
$requirementsValue = $_POST['requirements'] ?? '';
$requirementsValue = is_array($requirementsValue) ? ($requirementsValue[0] ?? '') : $requirementsValue;
$selectedCategoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$selectedCategoryName = $categoryNameById[$selectedCategoryId] ?? '';

// Map contact_position values to job title group labels
$positionToJobTitleGroup = [
    // Administrative / Office
    'Company Owner' => '🗂️ Administrative / Office',
    'Managing Director' => '🗂️ Administrative / Office',
    'Office Manager' => '🗂️ Administrative / Office',
    'Administrative Manager' => '🗂️ Administrative / Office',
    
    // Customer Service / BPO
    'BPO Company Owner' => '☎️ Customer Service / BPO',
    'Call Center Director' => '☎️ Customer Service / BPO',
    'Contact Center Manager' => '☎️ Customer Service / BPO',
    
    // Education
    'School Owner' => '🎓 Education',
    'School Director' => '🎓 Education',
    'Principal' => '🎓 Education',
    'Academic Director' => '🎓 Education',
    'Training Center Owner' => '🎓 Education',
    
    // Engineering
    'Engineering Firm Owner' => '⚙️ Engineering',
    'Engineering Director' => '⚙️ Engineering',
    'Project Director' => '⚙️ Engineering',
    'Engineering Manager' => '⚙️ Engineering',
    'Technical Manager' => '⚙️ Engineering',
    
    // Information Technology (IT)
    'IT Company Owner' => '💻 Information Technology (IT)',
    'Chief Technology Officer (CTO)' => '💻 Information Technology (IT)',
    'IT Director' => '💻 Information Technology (IT)',
    'Software Development Manager' => '💻 Information Technology (IT)',
    'Product Manager' => '💻 Information Technology (IT)',
    
    // Finance / Accounting
    'Finance Firm Owner' => '💰 Finance / Accounting',
    'Chief Financial Officer (CFO)' => '💰 Finance / Accounting',
    'Finance Director' => '💰 Finance / Accounting',
    'Accounting Manager' => '💰 Finance / Accounting',
    'Controller' => '💰 Finance / Accounting',
    
    // Healthcare / Medical
    'Hospital Owner' => '🏥 Healthcare / Medical',
    'Hospital Director' => '🏥 Healthcare / Medical',
    'Medical Director' => '🏥 Healthcare / Medical',
    'Clinic Owner' => '🏥 Healthcare / Medical',
    'Healthcare Administrator' => '🏥 Healthcare / Medical',
    
    // Human Resources (HR)
    'HR Director' => '👥 Human Resources (HR)',
    'HR Manager' => '👥 Human Resources (HR)',
    'People Operations Head' => '👥 Human Resources (HR)',
    'Recruitment Manager' => '👥 Human Resources (HR)',
    'Talent Director' => '👥 Human Resources (HR)',
    
    // Manufacturing / Production
    'Factory Owner' => '🏭 Manufacturing / Production',
    'Plant Manager' => '🏭 Manufacturing / Production',
    'Production Director' => '🏭 Manufacturing / Production',
    'Operations Director' => '🏭 Manufacturing / Production',
    'Manufacturing Manager' => '🏭 Manufacturing / Production',
    
    // Logistics / Warehouse / Supply Chain
    'Logistics Company Owner' => '🚚 Logistics / Warehouse / Supply Chain',
    'Supply Chain Director' => '🚚 Logistics / Warehouse / Supply Chain',
    'Logistics Manager' => '🚚 Logistics / Warehouse / Supply Chain',
    'Warehouse Manager' => '🚚 Logistics / Warehouse / Supply Chain',
    'Distribution Manager' => '🚚 Logistics / Warehouse / Supply Chain',
    
    // Marketing / Sales
    'Marketing Agency Owner' => '📈 Marketing / Sales',
    'Sales Director' => '📈 Marketing / Sales',
    'Marketing Director' => '📈 Marketing / Sales',
    'Business Development Manager' => '📈 Marketing / Sales',
    'Commercial Manager' => '📈 Marketing / Sales',
    
    // Creative / Media / Design
    'Creative Agency Owner' => '🎨 Creative / Media / Design',
    'Creative Director' => '🎨 Creative / Media / Design',
    'Art Director' => '🎨 Creative / Media / Design',
    'Studio Manager' => '🎨 Creative / Media / Design',
    'Content Director' => '🎨 Creative / Media / Design',
    
    // Construction / Infrastructure
    'Construction Company Owner' => '🏗️ Construction / Infrastructure',
    'Construction Manager' => '🏗️ Construction / Infrastructure',
    'Site Director' => '🏗️ Construction / Infrastructure',
    'Infrastructure Manager' => '🏗️ Construction / Infrastructure',
    
    // Food / Hospitality / Tourism
    'Restaurant Owner' => '🍽️ Food / Hospitality / Tourism (Fast-Food Included)',
    'Franchise Owner' => '🍽️ Food / Hospitality / Tourism (Fast-Food Included)',
    'Hotel/Resort Owner' => '🍽️ Food / Hospitality / Tourism (Fast-Food Included)',
    'Food & Beverage Director' => '🍽️ Food / Hospitality / Tourism (Fast-Food Included)',
    
    // Retail / Sales Operations
    'Store Owner' => '🛒 Retail / Sales Operations',
    'Retail Director' => '🛒 Retail / Sales Operations',
    'Branch Manager' => '🛒 Retail / Sales Operations',
    'Merchandising Manager' => '🛒 Retail / Sales Operations',
    
    // Transportation
    'Transport Company Owner' => '🚗 Transportation',
    'Fleet Manager' => '🚗 Transportation',
    'Transport Director' => '🚗 Transportation',
    'Terminal Manager' => '🚗 Transportation',
    
    // Law Enforcement / Criminology
    'Agency Director' => '👮 Law Enforcement / Criminology',
    'Station Commander' => '👮 Law Enforcement / Criminology',
    'Department Head' => '👮 Law Enforcement / Criminology',
    'Security Operations Director' => '👮 Law Enforcement / Criminology',
    'Law Enforcement Administrator' => '👮 Law Enforcement / Criminology',
    
    // Security Services
    'Security Agency Owner' => '🛡️ Security Services',
    'Security Director' => '🛡️ Security Services',
    'Risk Manager' => '🛡️ Security Services',
    'Compliance Head' => '🛡️ Security Services',
    
    // Skilled / Technical (TESDA)
    'Technical Director' => '🔧 Skilled / Technical (TESDA)',
    'Workshop Manager' => '🔧 Skilled / Technical (TESDA)',
    'Trade School Administrator' => '🔧 Skilled / Technical (TESDA)',
    
    // Note: "Operations Manager" appears in multiple groups, so we need to handle it based on context
    // For now, we'll use keyword matching or default to Administrative / Office
    // This will be handled in the filtering logic below
    
    // Agriculture / Fisheries
    'Farm Owner' => '🌾 Agriculture / Fisheries',
    'Agribusiness Director' => '🌾 Agriculture / Fisheries',
    'Plantation Manager' => '🌾 Agriculture / Fisheries',
    'Fisheries Manager' => '🌾 Agriculture / Fisheries',
    'Cooperative Manager' => '🌾 Agriculture / Fisheries',
    
    // Freelance / Online / Remote
    'Agency Owner' => '🌐 Freelance / Online / Remote',
    'Startup Founder' => '🌐 Freelance / Online / Remote',
    'Project Owner' => '🌐 Freelance / Online / Remote',
    'Platform Operator' => '🌐 Freelance / Online / Remote',
    'Remote Operations Manager' => '🌐 Freelance / Online / Remote',
    
    // Legal / Government / Public Service
    'Law Firm Owner' => '⚖️ Legal / Government / Public Service',
    'Managing Partner' => '⚖️ Legal / Government / Public Service',
    'Legal Director' => '⚖️ Legal / Government / Public Service',
    'Government Agency Head' => '⚖️ Legal / Government / Public Service',
    'Public Administrator' => '⚖️ Legal / Government / Public Service',
    
    // Maritime / Aviation / Transport Specialized
    'Shipping Company Owner' => '✈️ Maritime / Aviation / Transport Specialized',
    'Airline Director' => '✈️ Maritime / Aviation / Transport Specialized',
    'Fleet Director' => '✈️ Maritime / Aviation / Transport Specialized',
    'Port Manager' => '✈️ Maritime / Aviation / Transport Specialized',
    'Aviation Operations Manager' => '✈️ Maritime / Aviation / Transport Specialized',
    
    // Science / Research / Environment
    'Research Institute Director' => '🔬 Science / Research / Environment',
    'Laboratory Owner' => '🔬 Science / Research / Environment',
    'R&D Director' => '🔬 Science / Research / Environment',
    'Environmental Program Manager' => '🔬 Science / Research / Environment',
    'Science Center Administrator' => '🔬 Science / Research / Environment',
    
    // Arts / Entertainment / Culture
    'Production Company Owner' => '🎭 Arts / Entertainment / Culture',
    'Executive Producer' => '🎭 Arts / Entertainment / Culture',
    'Talent Agency Owner' => '🎭 Arts / Entertainment / Culture',
    'Arts Organization Director' => '🎭 Arts / Entertainment / Culture',
    
    // Religion / NGO / Development / Cooperative
    'NGO Founder' => '✝️ Religion / NGO / Development / Cooperative',
    'Executive Director' => '✝️ Religion / NGO / Development / Cooperative',
    'Program Director' => '✝️ Religion / NGO / Development / Cooperative',
    'Organization Head' => '✝️ Religion / NGO / Development / Cooperative',
    
    // Special / Rare Jobs
    'Specialist Firm Owner' => '🧩 Special / Rare Jobs',
    'Program Head' => '🧩 Special / Rare Jobs',
    'Industry Consultant (Owner)' => '🧩 Special / Rare Jobs',
    
    // Utilities / Public Services
    'Utilities Company Director' => '🔌 Utilities / Public Services',
    'Public Services Administrator' => '🔌 Utilities / Public Services',
    
    // Telecommunications
    'Telecom Company Owner' => '📡 Telecommunications',
    'Network Director' => '📡 Telecommunications',
    'Technical Operations Manager' => '📡 Telecommunications',
    
    // Mining / Geology
    'Mining Company Owner' => '⛏️ Mining / Geology',
    'Mine Director' => '⛏️ Mining / Geology',
    'Geology Manager' => '⛏️ Mining / Geology',
    
    // Operations Manager - appears in multiple groups, map based on keywords in company name or default
    // We'll handle this with a fallback mechanism
    
    // Oil / Gas / Energy
    'Energy Company Owner' => '🛢️ Oil / Gas / Energy',
    'Energy Operations Director' => '🛢️ Oil / Gas / Energy',
    
    // Chemical / Industrial
    'Chemical Plant Owner' => '⚗️ Chemical / Industrial',
    'Industrial Director' => '⚗️ Chemical / Industrial',
    'Quality Director' => '⚗️ Chemical / Industrial',
    
    // Allied Health / Special Education / Therapy
    'Therapy Center Owner' => '🩺 Allied Health / Special Education / Therapy',
    'Clinical Director' => '🩺 Allied Health / Special Education / Therapy',
    'Healthcare Program Manager' => '🩺 Allied Health / Special Education / Therapy',
    'Special Education Director' => '🩺 Allied Health / Special Education / Therapy',
    
    // Sports / Fitness / Recreation
    'Gym Owner' => '🏋️ Sports / Fitness / Recreation',
    'Sports Director' => '🏋️ Sports / Fitness / Recreation',
    'Fitness Operations Manager' => '🏋️ Sports / Fitness / Recreation',
    'Recreation Center Manager' => '🏋️ Sports / Fitness / Recreation',
    
    // Fashion / Apparel / Beauty
    'Fashion Brand Owner' => '👗 Fashion / Apparel / Beauty',
    'Salon Owner' => '👗 Fashion / Apparel / Beauty',
    
    // Home / Personal Services
    'Service Business Owner' => '🏡 Home / Personal Services',
    'Agency Manager' => '🏡 Home / Personal Services',
    
    // Insurance / Risk / Banking
    'Insurance Company Owner' => '🏦 Insurance / Risk / Banking',
    'Risk Director' => '🏦 Insurance / Risk / Banking',
    'Banking Operations Manager' => '🏦 Insurance / Risk / Banking',
    
    // Freelance / Online / Remote - specific operations manager
    'Remote Operations Manager' => '🌐 Freelance / Online / Remote',
    
    // Maritime / Aviation - specific operations manager
    'Aviation Operations Manager' => '✈️ Maritime / Aviation / Transport Specialized',
    
    // Sports / Fitness - specific operations manager
    'Fitness Operations Manager' => '🏋️ Sports / Fitness / Recreation',
    
    // Micro Jobs / Informal / Daily Wage
    'Small Business Owner' => '💼 Micro Jobs / Informal / Daily Wage Jobs',
    'Contractor' => '💼 Micro Jobs / Informal / Daily Wage Jobs',
    'Project Supervisor' => '💼 Micro Jobs / Informal / Daily Wage Jobs',
    'Operations Head' => '💼 Micro Jobs / Informal / Daily Wage Jobs',
    
    // Real Estate / Property
    'Property Developer' => '🏠 Real Estate / Property',
    'Real Estate Company Owner' => '🏠 Real Estate / Property',
    'Property Manager' => '🏠 Real Estate / Property',
    'Leasing Director' => '🏠 Real Estate / Property',
    
    // Entrepreneurship / Business / Corporate
    'Entrepreneur' => '📊 Entrepreneurship / Business / Corporate',
    'Founder / Co-Founder' => '📊 Entrepreneurship / Business / Corporate',
    'Chief Executive Officer (CEO)' => '📊 Entrepreneurship / Business / Corporate',
    'Business Owner' => '📊 Entrepreneurship / Business / Corporate',
];

$jobTitleGroups = [
    '🗂️ Administrative / Office' => [
        'Office Administrator',
        'Executive Assistant',
        'Administrative Coordinator',
        'Data Entry Clerk',
        'Office Manager',
        'Receptionist',
        'Personal Assistant',
        'Administrative Officer',
        'Records Clerk',
        'Operations Assistant',
        'Secretary',
        'Front Desk Officer',
        'Executive Secretary',
        'Office Clerk',
        'Filing Clerk',
        'Scheduling Coordinator',
        'Office Services Manager',
        'Documentation Specialist',
        'Office Support Specialist',
        'Office Supervisor'
    ],
    '☎️ Customer Service / BPO' => [
        'Customer Service Representative',
        'Call Center Agent',
        'Client Support Specialist',
        'Help Desk Associate',
        'Customer Care Coordinator',
        'Technical Support Representative',
        'Service Desk Analyst',
        'Account Support Specialist',
        'Call Center Supervisor',
        'Customer Experience Associate',
        'Contact Center Trainer',
        'Chat Support Agent',
        'Email Support Specialist',
        'Escalation Officer',
        'QA Analyst (Customer Service)',
        'Customer Retention Specialist',
        'Virtual Customer Service Associate',
        'Inside Sales / Customer Support',
        'Team Lead – Customer Support'
    ],
    '🎓 Education' => [
        'Teacher',
        'School Counselor',
        'Academic Coordinator',
        'Tutor',
        'Principal',
        'Librarian',
        'Special Education Teacher',
        'Curriculum Developer',
        'Education Program Manager',
        'Lecturer',
        'College Instructor',
        'Preschool Teacher',
        'Teaching Assistant',
        'Instructional Designer',
        'Learning Facilitator',
        'Education Consultant',
        'Homeroom Teacher',
        'School Administrator',
        'Guidance Counselor',
        'Academic Adviser'
    ],
    '⚙️ Engineering' => [
        'Civil Engineer',
        'Mechanical Engineer',
        'Electrical Engineer',
        'Project Engineer',
        'Structural Engineer',
        'Chemical Engineer',
        'Industrial Engineer',
        'Process Engineer',
        'Quality Engineer',
        'Design Engineer',
        'Maintenance Engineer',
        'Field Engineer',
        'Systems Engineer',
        'Engineering Technician',
        'Automation Engineer',
        'Product Design Engineer',
        'Control Systems Engineer',
        'Environmental Engineer',
        'Safety Engineer',
        'Reliability Engineer'
    ],
    '💻 Information Technology (IT)' => [
        'Software Developer',
        'Network Administrator',
        'IT Support Specialist',
        'Web Developer',
        'Systems Analyst',
        'Database Administrator',
        'Cybersecurity Analyst',
        'Cloud Engineer',
        'IT Manager',
        'Technical Lead',
        'Application Developer',
        'DevOps Engineer',
        'Mobile App Developer',
        'Data Engineer',
        'Network Security Engineer',
        'IT Project Manager',
        'UX/UI Developer',
        'Front-End Developer',
        'Back-End Developer',
        'IT Infrastructure Engineer',
        'IT Consultant',
        'IT Auditor'
    ],
    '💰 Finance / Accounting' => [
        'Accountant',
        'Financial Analyst',
        'Bookkeeper',
        'Payroll Officer',
        'Tax Specialist',
        'Budget Analyst',
        'Auditor',
        'Finance Manager',
        'Credit Analyst',
        'Controller',
        'Cost Accountant',
        'Treasury Analyst',
        'Accounts Payable Clerk',
        'Accounts Receivable Clerk',
        'Finance Officer',
        'Investment Analyst',
        'Risk Officer',
        'Compliance Officer – Finance',
        'Loan Officer',
        'Fund Accountant',
        'Billing Officer',
        'Treasury Officer'
    ],
    '🏥 Healthcare / Medical' => [
        'Doctor',
        'Nurse',
        'Medical Technologist',
        'Physician',
        'Pharmacist',
        'Dentist',
        'Radiologic Technologist',
        'Physical Therapist',
        'Occupational Therapist',
        'Laboratory Technician',
        'Midwife',
        'Paramedic',
        'Dietitian',
        'Nurse Practitioner',
        'Anesthesiologist',
        'Surgeon',
        'Medical Assistant',
        'Health Information Technician',
        'Speech Therapist',
        'Psychologist',
        'Care Coordinator',
        'Emergency Medical Technician',
        'Clinical Coordinator'
    ],
    '👥 Human Resources (HR)' => [
        'HR Manager',
        'Recruitment Specialist',
        'HR Generalist',
        'Training Coordinator',
        'Talent Acquisition Officer',
        'Compensation & Benefits Specialist',
        'HR Assistant',
        'Employee Relations Officer',
        'HR Business Partner',
        'Learning & Development Officer',
        'HR Coordinator',
        'Payroll Specialist',
        'HR Analyst',
        'Recruitment Coordinator',
        'HR Consultant',
        'Onboarding Specialist',
        'HR Officer',
        'HR Administrator'
    ],
    '🏭 Manufacturing / Production' => [
        'Production Supervisor',
        'Machine Operator',
        'Quality Control Inspector',
        'Plant Manager',
        'Production Planner',
        'Assembler',
        'Factory Worker',
        'Manufacturing Engineer',
        'Line Supervisor',
        'Shift Supervisor',
        'Inventory Controller',
        'Process Operator',
        'Production Technician',
        'Packaging Operator',
        'Production Scheduler',
        'Operations Supervisor',
        'Plant Technician'
    ],
    '🚚 Logistics / Warehouse / Supply Chain' => [
        'Warehouse Supervisor',
        'Logistics Coordinator',
        'Inventory Clerk',
        'Supply Chain Analyst',
        'Shipping & Receiving Clerk',
        'Transport Planner',
        'Procurement Officer',
        'Fleet Manager',
        'Distribution Manager',
        'Order Fulfillment Officer',
        'Warehouse Staff',
        'Logistics Officer',
        'Stock Controller',
        'Delivery Coordinator',
        'Supply Officer',
        'Logistics Manager'
    ],
    '📈 Marketing / Sales' => [
        'Marketing Specialist',
        'Sales Executive',
        'Brand Manager',
        'Account Manager',
        'Social Media Manager',
        'Marketing Coordinator',
        'Business Development Officer',
        'Advertising Specialist',
        'Digital Marketing Analyst',
        'Product Manager',
        'Sales Supervisor',
        'Key Account Manager',
        'Territory Sales Manager',
        'Marketing Analyst',
        'Event Marketing Coordinator',
        'Promotions Officer'
    ],
    '🎨 Creative / Media / Design' => [
        'Graphic Designer',
        'Video Editor',
        'Content Creator',
        'Art Director',
        'Illustrator',
        'Photographer',
        'Animator',
        'Copywriter',
        'UX/UI Designer',
        'Creative Director',
        'Visual Designer',
        'Motion Graphics Designer',
        'Web Designer',
        'Production Designer',
        'Layout Artist'
    ],
    '🏗️ Construction / Infrastructure' => [
        'Construction Manager',
        'Site Engineer',
        'Architect',
        'Foreman',
        'Project Manager',
        'Quantity Surveyor',
        'Civil Technician',
        'Structural Designer',
        'Safety Officer',
        'Building Inspector',
        'Construction Supervisor',
        'Field Engineer',
        'Project Engineer',
        'Site Supervisor',
        'Estimator'
    ],
    '🍽️ Food / Hospitality / Tourism' => [
        'Chef',
        'Sous Chef',
        'Line Cook',
        'Prep Cook',
        'Grill Cook',
        'Fry Cook',
        'Breakfast Cook',
        'Pastry / Dessert Cook',
        'Baker',
        'Barista',
        'Crew Member',
        'Restaurant Manager',
        'Kitchen Staff',
        'Shift Supervisor',
        'Fast Food Crew',
        'Cashier',
        'Host / Hostess',
        'Food Runner',
        'Waiter / Waitress',
        'Bartender',
        'Hotel Front Desk Officer',
        'Concierge',
        'Tour Guide',
        'Event Coordinator',
        'Catering Staff'
    ],
    '🛒 Retail / Sales Operations' => [
        'Store Manager',
        'Sales Associate',
        'Merchandiser',
        'Cashier',
        'Retail Supervisor',
        'Stock Clerk',
        'Floor Manager',
        'Visual Merchandiser',
        'Sales Coordinator',
        'Customer Service Associate',
        'Assistant Store Manager',
        'Key Account Executive',
        'Sales Representative',
        'Inventory Clerk',
        'Retail Sales Officer',
        'Shop Attendant',
        'Display Coordinator'
    ],
    '🚗 Transportation' => [
        'Driver',
        'Delivery Rider',
        'Fleet Manager',
        'Transport Coordinator',
        'Logistics Driver',
        'Bus Driver',
        'Taxi Driver',
        'Air Cargo Handler',
        'Dispatch Officer',
        'Vehicle Inspector',
        'Truck Driver',
        'Shuttle Driver',
        'Transportation Officer',
        'Delivery Supervisor'
    ],
    '👮 Law Enforcement / Criminology' => [
        'Police Officer',
        'Detective',
        'Crime Scene Investigator',
        'Security Analyst',
        'Forensic Specialist',
        'Corrections Officer',
        'Crime Analyst',
        'Intelligence Officer',
        'Patrol Officer',
        'Investigation Officer',
        'Police Chief',
        'Detective Sergeant',
        'Crime Prevention Officer',
        'Forensic Analyst'
    ],
    '🛡️ Security Services' => [
        'Security Guard',
        'Security Supervisor',
        'Loss Prevention Officer',
        'Bodyguard',
        'Security Coordinator',
        'Alarm Systems Officer',
        'CCTV Operator',
        'Security Consultant',
        'Executive Protection Officer',
        'Event Security Officer',
        'Security Officer',
        'Security Manager',
        'Safety and Security Officer'
    ],
    '🔧 Skilled / Technical (TESDA)' => [
        'Electrician',
        'Welder',
        'Automotive Technician',
        'Carpenter',
        'Plumber',
        'Mason',
        'HVAC Technician',
        'CNC Operator',
        'Industrial Technician',
        'Electronics Technician',
        'Refrigeration Technician',
        'Machinist',
        'Fabricator',
        'Pipefitter',
        'Maintenance Technician',
        'Tool and Die Maker'
    ],
    '🌾 Agriculture / Fisheries' => [
        'Farm Manager',
        'Agronomist',
        'Fishery Technician',
        'Agricultural Laborer',
        'Crop Specialist',
        'Livestock Technician',
        'Farm Equipment Operator',
        'Agriculture Extension Officer',
        'Horticulturist',
        'Aquaculture Specialist',
        'Plantation Supervisor',
        'Farm Inspector',
        'Soil Scientist',
        'Agriculture Technician'
    ],
    '🌐 Freelance / Online / Remote' => [
        'Virtual Assistant',
        'Freelance Writer',
        'Online Tutor',
        'Graphic Designer',
        'Content Creator',
        'Social Media Manager',
        'Web Developer',
        'Data Entry Specialist',
        'Translator',
        'Remote Customer Support',
        'Online Consultant',
        'SEO Specialist',
        'Digital Marketing Freelancer',
        'Video Editor – Remote'
    ],
    '⚖️ Legal / Government / Public Service' => [
        'Lawyer',
        'Paralegal',
        'Government Officer',
        'Legal Assistant',
        'Policy Analyst',
        'Court Clerk',
        'Compliance Officer',
        'Public Administrator',
        'Legal Researcher',
        'Legal Consultant',
        'Judicial Clerk',
        'Public Policy Officer',
        'Court Officer',
        'Administrative Law Officer'
    ],
    '✈️ Maritime / Aviation / Transport Specialized' => [
        'Ship Captain',
        'Pilot',
        'Flight Attendant',
        'Marine Engineer',
        'Deck Officer',
        'Air Traffic Controller',
        'Ship Engineer',
        'Cabin Crew',
        'Marine Technician',
        'Aviation Safety Officer',
        'Port Officer',
        'Harbor Master',
        'Flight Dispatcher'
    ],
    '🔬 Science / Research / Environment' => [
        'Research Scientist',
        'Laboratory Technician',
        'Environmental Officer',
        'Data Analyst',
        'Biochemist',
        'Ecologist',
        'Field Researcher',
        'Microbiologist',
        'Environmental Consultant',
        'Lab Assistant',
        'Research Assistant',
        'Marine Biologist',
        'Laboratory Analyst',
        'Climate Scientist'
    ],
    '🎭 Arts / Entertainment / Culture' => [
        'Actor',
        'Musician',
        'Dancer',
        'Cultural Program Coordinator',
        'Singer',
        'Director',
        'Photographer',
        'Art Curator',
        'Theater Performer',
        'Costume Designer',
        'Visual Artist',
        'Film Editor',
        'Choreographer',
        'Stage Manager'
    ],
    '✝️ Religion / NGO / Development / Cooperative' => [
        'Pastor',
        'NGO Program Officer',
        'Social Worker',
        'Community Organizer',
        'Missionary',
        'Development Officer',
        'Volunteer Coordinator',
        'Church Administrator',
        'Program Manager',
        'Cooperative Manager',
        'Field Officer – NGO',
        'Project Officer – NGO',
        'Community Development Officer'
    ],
    '🧩 Special / Rare Jobs' => [
        'Ethical Hacker',
        'Stunt Performer',
        'Ice Sculptor',
        'Professional Gamer',
        'Escape Room Designer',
        'Drone Operator',
        'Voice Actor',
        'Extreme Sports Instructor',
        'Special Effects Artist',
        'Magician',
        'Mystery Shopper',
        'Puppeteer',
        'Forensic Artist'
    ],
    '🔌 Utilities / Public Services' => [
        'Electrician',
        'Water Plant Operator',
        'Utility Technician',
        'Meter Reader',
        'Waste Management Officer',
        'Line Worker',
        'Public Utility Engineer',
        'Maintenance Technician',
        'Facility Officer',
        'Energy Technician',
        'Water Treatment Technician',
        'Power Plant Operator'
    ],
    '📡 Telecommunications' => [
        'Telecommunications Technician',
        'Network Engineer',
        'Customer Support Specialist',
        'Field Engineer',
        'Tower Technician',
        'Telecom Analyst',
        'Fiber Optic Technician',
        'VoIP Specialist',
        'RF Engineer',
        'Service Coordinator',
        'Telecom Sales Officer',
        'Network Installation Technician'
    ],
    '⛏️ Mining / Geology' => [
        'Geologist',
        'Mining Engineer',
        'Drill Operator',
        'Safety Officer',
        'Surveyor',
        'Mine Technician',
        'Geotechnical Engineer',
        'Mineral Analyst',
        'Exploration Officer',
        'Quarry Supervisor',
        'Mine Surveyor',
        'Mining Safety Engineer'
    ],
    '🛢️ Oil / Gas / Energy' => [
        'Petroleum Engineer',
        'Safety Officer',
        'Energy Analyst',
        'Plant Operator',
        'Drilling Engineer',
        'Maintenance Technician',
        'Field Operator',
        'Pipeline Engineer',
        'Energy Consultant',
        'Refinery Technician',
        'Production Engineer – Oil & Gas',
        'Offshore Rig Technician'
    ],
    '⚗️ Chemical / Industrial' => [
        'Chemical Engineer',
        'Laboratory Technician',
        'Process Operator',
        'Quality Analyst',
        'Production Chemist',
        'Industrial Technician',
        'Safety Officer',
        'Formulation Specialist',
        'Research Chemist',
        'Control Room Operator',
        'Plant Chemist',
        'Industrial Safety Officer'
    ],
    '🩺 Allied Health / Special Education / Therapy' => [
        'Physical Therapist',
        'Occupational Therapist',
        'Speech Therapist',
        'Special Educator',
        'Rehabilitation Specialist',
        'Psychologist',
        'Audiologist',
        'Orthotist',
        'Prosthetist',
        'Behavioral Therapist',
        'Therapy Assistant',
        'Learning Support Officer'
    ],
    '🏋️ Sports / Fitness / Recreation' => [
        'Fitness Trainer',
        'Coach',
        'Sports Analyst',
        'Recreation Coordinator',
        'Gym Instructor',
        'Yoga Instructor',
        'Athletic Trainer',
        'Sports Official',
        'Lifeguard',
        'Wellness Coach',
        'Personal Trainer',
        'Sports Physiotherapist'
    ],
    '👗 Fashion / Apparel / Beauty' => [
        'Fashion Designer',
        'Stylist',
        'Makeup Artist',
        'Boutique Manager',
        'Hairdresser',
        'Fashion Merchandiser',
        'Nail Technician',
        'Costume Designer',
        'Wardrobe Consultant',
        'Beauty Therapist',
        'Fashion Illustrator',
        'Image Consultant'
    ],
    '🍔 Food Service / Fast Food / QSR' => [
        'Cook',
        'Barista',
        'Crew Member',
        'Restaurant Manager',
        'Kitchen Staff',
        'Shift Supervisor',
        'Fast Food Crew',
        'Cashier',
        'Host/Hostess',
        'Food Runner'
    ],
    '🏡 Home / Personal Services' => [
        'Housekeeper',
        'Nanny',
        'Caregiver',
        'Personal Trainer',
        'Driver',
        'Gardener',
        'Elderly Care Assistant',
        'Pet Groomer',
        'Laundry Attendant',
        'Babysitter',
        'Home Care Aide',
        'Personal Assistant – Household'
    ],
    '🏦 Insurance / Risk / Banking' => [
        'Insurance Agent',
        'Risk Analyst',
        'Loan Officer',
        'Banking Teller',
        'Claims Adjuster',
        'Underwriter',
        'Financial Advisor',
        'Credit Analyst',
        'Investment Officer',
        'Policy Consultant',
        'Branch Banking Officer',
        'Insurance Underwriting Assistant'
    ],
    '💼 Micro Jobs / Informal / Daily Wage Jobs' => [
        'Delivery Rider',
        'Vendor',
        'Street Cleaner',
        'Construction Laborer',
        'Messenger',
        'Market Seller',
        'Driver',
        'Helper',
        'Day Laborer',
        'Errand Runner',
        'Food Cart Vendor',
        'Gig Worker'
    ],
    '🏠 Real Estate / Property' => [
        'Real Estate Agent',
        'Property Manager',
        'Leasing Officer',
        'Appraiser',
        'Broker',
        'Real Estate Consultant',
        'Valuation Officer',
        'Sales Executive',
        'Development Manager',
        'Estate Manager',
        'Rental Officer',
        'Property Leasing Specialist'
    ],
    '📊 Entrepreneurship / Business / Corporate' => [
        'Chief Executive Officer',
        'Startup Founder',
        'Business Analyst',
        'Operations Manager',
        'Project Manager',
        'Management Consultant',
        'Entrepreneur',
        'Strategic Planner',
        'Corporate Officer',
        'Business Development Manager',
        'Operations Analyst',
        'Executive Director'
    ]
];

// Filter job titles based on company's contact_position
// Store original groups for reference
$originalJobTitleGroups = $jobTitleGroups;
if (!empty($company['contact_position'])) {
    $contactPosition = $company['contact_position'];
    
    // Check if position is in the mapping
    if (isset($positionToJobTitleGroup[$contactPosition])) {
        $allowedGroup = $positionToJobTitleGroup[$contactPosition];
        // Keep only the job title group that matches the company's position
        $filteredJobTitleGroups = [];
        if (isset($jobTitleGroups[$allowedGroup])) {
            $filteredJobTitleGroups[$allowedGroup] = $jobTitleGroups[$allowedGroup];
            $jobTitleGroups = $filteredJobTitleGroups;
        }
    }
    // If position doesn't match any group, show all (fallback - allows flexibility)
}

// Kapag na-filter by POSITION (isang group lang), alamin ang Category value at display para sa dropdown
$filteredByPosition = (count($jobTitleGroups) === 1);
$filteredCategoryValue = null;
$filteredCategoryDisplay = null;
if ($filteredByPosition) {
    $groupLabel = array_key_first($jobTitleGroups);
    $filteredCategoryDisplay = $groupLabel; // may emoji/logo (e.g. 🎓 Education)
    $groupLabelToCategoryOptionValue = [
        '🍽️ Food / Hospitality / Tourism' => 'Food / Hospitality / Tourism (including Fast-Food Chains)',
        '🍔 Food Service / Fast Food / QSR' => 'Food / Hospitality / Tourism (including Fast-Food Chains)',
    ];
    $filteredCategoryValue = $groupLabelToCategoryOptionValue[$groupLabel] ?? preg_replace('/^[^\p{L}\p{N}\s\/\-\(\)]*\s*/u', '', $groupLabel);
}

$categorySkillOptions = [
    'administrative' => [
        'Administrative',
        'Organization and time management',
        'Communication skills (written and verbal)',
        'Microsoft Office / Google Workspace proficiency',
        'Scheduling and calendar management',
        'Data entry accuracy',
        'Filing and record keeping',
        'Problem-solving and decision-making',
        'Multitasking and prioritization',
        'Attention to detail',
        'Customer service skills',
        'Office operations management',
        'Document & records control',
        'MS Office proficiency',
        'Scheduling & coordination',
        'Basic accounting / reports',
        'Communication skills',
        'Executive calendar & travel management',
        'Confidential document handling',
        'Meeting coordination',
        'Email & correspondence management',
        'Report & presentation preparation',
        'High-level communication',
        'Office activity coordination',
        'Scheduling & logistics',
        'Documentation & reporting',
        'Inter-department communication',
        'Data management',
        'Time management',
        'Fast & accurate typing',
        'Data encoding & validation',
        'Basic MS Excel',
        'File organization',
        'Confidential data handling',
        'Office operations supervision',
        'Staff management',
        'Budget & expense monitoring',
        'Policy implementation',
        'Vendor coordination',
        'Problem-solving & leadership',
        'Front desk operations',
        'Phone & email handling',
        'Visitor management',
        'Customer service',
        'Appointment scheduling',
        'Professional communication',
        'Task & schedule management',
        'Travel & meeting arrangements',
        'Confidentiality handling',
        'Email correspondence',
        'Multitasking skills',
        'Administrative process management',
        'Records & documentation',
        'Office coordination',
        'Report preparation',
        'Compliance monitoring',
        'Organizational skills',
        'Records filing & indexing',
        'Document retrieval',
        'Data accuracy',
        'Confidentiality compliance',
        'Inventory of files',
        'Daily operations support',
        'Data tracking & reporting',
        'Process coordination',
        'Inventory monitoring',
        'Team support',
        'Problem-solving',
        'Office correspondence',
        'Minutes of meetings',
        'Document preparation',
        'Phone handling',
        'Visitor registration',
        'Information handling',
        'Professional etiquette',
        'Executive support functions',
        'Confidential documentation',
        'Communication coordination',
        'Office organization',
        'General clerical tasks',
        'Office supply management',
        'Basic computer skills',
        'Document sorting & filing',
        'Record maintenance',
        'Accuracy checking',
        'File retrieval'
    ],
    'customer service' => [
        'Customer Service',
        'Excellent communication skills',
        'Active listening',
        'Problem-solving abilities',
        'Patience and empathy',
        'Conflict resolution',
        'Product/service knowledge',
        'CRM software proficiency',
        'Multitasking',
        'Time management',
        'Technical troubleshooting (for technical support roles)',
        'Customer communication (phone, email, chat)',
        'Problem-solving & issue resolution',
        'Basic CRM tools',
        'Data entry & documentation',
        'Inbound/outbound call handling',
        'Customer support & inquiry resolution',
        'Script adherence & call documentation',
        'Stress management',
        'Client communication & support',
        'Troubleshooting client issues',
        'CRM & ticketing system usage',
        'Follow-ups & escalation handling',
        'Analytical thinking',
        'Technical support (software/hardware)',
        'Issue triaging & troubleshooting',
        'Ticket management',
        'Customer guidance & instruction',
        'Documentation & reporting',
        'Basic IT knowledge',
        'Customer support coordination',
        'Complaint resolution',
        'Client satisfaction monitoring',
        'Scheduling follow-ups',
        'Reporting & analysis',
        'Communication & interpersonal skills',
        'Technical troubleshooting',
        'Product/service guidance',
        'Ticketing systems',
        'Knowledge base utilization',
        'Problem-solving & escalation',
        'Clear technical communication',
        'IT/service support',
        'Incident management',
        'Problem analysis & resolution',
        'Ticket logging & reporting',
        'SLA adherence',
        'Technical documentation',
        'Customer account management',
        'Issue tracking & resolution',
        'Data entry & reporting',
        'CRM tools proficiency',
        'Relationship building',
        'Analytical skills',
        'Team supervision & coaching',
        'Performance monitoring',
        'KPI tracking',
        'Scheduling & workload management',
        'Communication & leadership',
        'Customer journey monitoring',
        'Feedback collection & analysis',
        'Issue resolution & escalation',
        'CRM familiarity',
        'Empathy & problem-solving',
        'Training design & delivery',
        'Coaching & mentoring',
        'Performance evaluation',
        'Curriculum preparation',
        'Communication & presentation',
        'Knowledge of tools & systems',
        'Online chat handling',
        'Typing speed & accuracy',
        'Email correspondence',
        'Written communication',
        'Issue documentation',
        'Problem-solving & follow-up',
        'Professional tone & clarity',
        'Handling complex complaints',
        'Escalation resolution',
        'Communication & negotiation',
        'Analytical problem-solving',
        'Client satisfaction focus',
        'Call/chat/email quality monitoring',
        'Process compliance evaluation',
        'Feedback & coaching',
        'Report generation',
        'Attention to detail',
        'Client relationship building',
        'CRM management',
        'Upselling & cross-selling',
        'Customer satisfaction focus',
        'Remote customer support',
        'Task management',
        'Communication (email, chat, call)',
        'CRM & ticketing tools',
        'Self-discipline',
        'Lead handling & conversion',
        'Customer relationship management',
        'Communication & persuasion skills',
        'Team management & coaching',
        'Workflow planning',
        'Performance evaluation',
        'Strong communication & leadership'
    ],
    'education' => [
        'Lesson planning and curriculum knowledge',
        'Classroom management',
        'Communication and interpersonal skills',
        'Subject matter expertise',
        'Patience and adaptability',
        'Assessment and evaluation skills',
        'Counseling and mentoring (for counselors)',
        'Knowledge of educational technologies',
        'Collaboration with colleagues',
        'Creativity in teaching methods',
        'Lesson planning & delivery',
        'Student assessment & evaluation',
        'Communication & presentation skills',
        'Student guidance & support',
        'Academic & career counseling',
        'Emotional & social development assistance',
        'Communication & empathy',
        'Crisis intervention',
        'Record keeping & reporting',
        'Curriculum planning & implementation',
        'Scheduling & coordination',
        'Teacher support & supervision',
        'Data tracking & reporting',
        'Communication & organizational skills',
        'Problem-solving',
        'Lesson preparation',
        'One-on-one teaching',
        'Student assessment',
        'Communication & patience',
        'Motivation & mentoring',
        'School leadership & administration',
        'Staff management & supervision',
        'Policy implementation',
        'Budgeting & resource management',
        'Conflict resolution',
        'Communication & decision-making',
        'Cataloging & classification',
        'Library management systems',
        'Research assistance',
        'Information organization & retrieval',
        'Customer service',
        'Individualized Education Program (IEP) planning',
        'Inclusive teaching strategies',
        'Student assessment & monitoring',
        'Behavior management',
        'Collaboration with parents & staff',
        'Curriculum design & evaluation',
        'Instructional material creation',
        'Learning outcomes assessment',
        'Pedagogy knowledge',
        'Research & analysis',
        'Program planning & coordination',
        'Staff & budget management',
        'Reporting & documentation',
        'Stakeholder communication',
        'Project management',
        'Evaluation & assessment',
        'Lesson delivery & presentation',
        'Student engagement & mentoring',
        'Research & publication',
        'Assessment & grading',
        'Course planning & instruction',
        'Student evaluation',
        'Curriculum alignment',
        'Mentorship & advising',
        'Communication & presentation skills',
        'Research & academic writing',
        'Early childhood education',
        'Lesson planning & creativity',
        'Child development understanding',
        'Parent engagement',
        'Classroom support',
        'Lesson preparation assistance',
        'Student monitoring & feedback',
        'Communication & teamwork',
        'Administrative support',
        'Tutoring & mentoring',
        'Course design & development',
        'Learning materials creation',
        'E-learning & multimedia tools',
        'Pedagogy & curriculum knowledge',
        'Assessment design',
        'Workshop & training delivery',
        'Adult learning principles',
        'Student engagement',
        'Communication & facilitation',
        'Assessment & feedback',
        'Instructional planning',
        'Education program evaluation',
        'Policy & curriculum advising',
        'Stakeholder engagement',
        'Communication & presentation',
        'Student monitoring & mentoring',
        'Communication with parents',
        'Record keeping',
        'Student engagement',
        'School operations management',
        'Staff supervision & coordination',
        'Budgeting & resource allocation',
        'Communication & leadership',
        'Emotional & social development support',
        'Crisis management',
        'Academic planning & guidance',
        'Student counseling & mentoring',
        'Course & curriculum advising',
        'Record tracking & reporting',
        'Problem-solving & support'
    ],
    'engineering' => [
        'Technical and analytical skills',
        'Problem-solving and critical thinking',
        'Knowledge of engineering software (AutoCAD, MATLAB, SolidWorks, etc.)',
        'Project management',
        'Attention to detail',
        'Communication skills',
        'Teamwork and collaboration',
        'Knowledge of safety standards and regulations',
        'Design and testing skills',
        'Continuous learning and innovation',
        'Structural analysis & design',
        'Construction planning',
        'AutoCAD & design software',
        'Site supervision',
        'Problem-solving & safety compliance',
        'Mechanical system design & analysis',
        'CAD & SolidWorks',
        'Equipment maintenance & troubleshooting',
        'Thermodynamics & materials knowledge',
        'Technical documentation',
        'Electrical system design',
        'Circuit analysis & troubleshooting',
        'PLC programming',
        'Safety & compliance',
        'Project coordination',
        'Project planning & execution',
        'Budgeting & cost control',
        'Scheduling & coordination',
        'Risk assessment',
        'Technical documentation',
        'Team collaboration',
        'Safety & compliance standards',
        'AutoCAD & modeling software',
        'Material specification',
        'Report writing & documentation',
        'Process design & optimization',
        'Laboratory testing',
        'Equipment operation & troubleshooting',
        'Data analysis & reporting',
        'Process improvement',
        'Process optimization & workflow analysis',
        'Lean manufacturing & Six Sigma basics',
        'Productivity monitoring',
        'Safety & quality standards',
        'Data analysis & reporting',
        'Process design & improvement',
        'Equipment specification & troubleshooting',
        'Quality control',
        'Safety compliance',
        'Data analysis & documentation',
        'Quality assurance & control',
        'Process evaluation',
        'Compliance & standards monitoring',
        'Auditing & inspection',
        'Documentation & reporting',
        'CAD / 3D modeling',
        'Product design & prototyping',
        'Materials & technical specifications',
        'Project collaboration',
        'Documentation',
        'Equipment maintenance & troubleshooting',
        'Preventive maintenance planning',
        'Technical problem-solving',
        'Team coordination',
        'Record keeping',
        'On-site project supervision',
        'Equipment & system troubleshooting',
        'Client coordination',
        'Technical reporting',
        'Systems design & integration',
        'Troubleshooting & optimization',
        'Requirements analysis',
        'Communication & collaboration',
        'Technical support for engineering tasks',
        'Equipment operation & testing',
        'Drafting & CAD support',
        'Quality checks',
        'PLC & control systems',
        'Robotics & automation programming',
        'Troubleshooting & maintenance',
        'Product concept & prototyping',
        'Material selection',
        'Testing & validation',
        'Collaboration with R&D & manufacturing',
        'Control system design & programming',
        'PLC & SCADA knowledge',
        'Data acquisition & analysis',
        'Environmental impact assessment',
        'Regulatory compliance',
        'Waste management & pollution control',
        'Data collection & analysis',
        'Report writing',
        'Safety audits & risk assessment',
        'Compliance with OSHA/standards',
        'Incident investigation',
        'Training & awareness programs',
        'Emergency response planning',
        'Equipment reliability analysis',
        'Maintenance planning & optimization',
        'Data-driven problem-solving',
        'Risk assessment',
        'Preventive maintenance strategies',
        'Technical reporting'
    ],
    'finance' => [
        'Accounting and bookkeeping',
        'Financial analysis and reporting',
        'Knowledge of financial software (QuickBooks, SAP, Excel)',
        'Budgeting and forecasting',
        'Taxation knowledge',
        'Attention to detail and accuracy',
        'Risk assessment',
        'Regulatory compliance',
        'Analytical thinking',
        'Communication skills',
        'Financial reporting & analysis',
        'Bookkeeping & ledger management',
        'Tax compliance & preparation',
        'Budget monitoring',
        'MS Excel & accounting software',
        'Attention to detail',
        'Financial modeling & forecasting',
        'Data analysis & interpretation',
        'Budget & variance analysis',
        'Report preparation & presentation',
        'Communication & business acumen',
        'Problem-solving',
        'Recording financial transactions',
        'Accounts reconciliation',
        'Invoice & expense tracking',
        'Basic financial reporting',
        'Accuracy & attention to detail',
        'Payroll processing & compliance',
        'Tax & statutory deductions',
        'Employee record maintenance',
        'HR & payroll system proficiency',
        'Confidentiality & accuracy',
        'Reporting',
        'Tax planning & filing',
        'Regulatory compliance',
        'Tax research & advisory',
        'Financial record analysis',
        'Communication with authorities',
        'Budget preparation & monitoring',
        'Variance & trend analysis',
        'Financial forecasting',
        'Reporting & recommendations',
        'Collaboration with departments',
        'Internal/external audits',
        'Compliance & risk assessment',
        'Financial statement review',
        'Documentation & reporting',
        'Analytical skills',
        'Communication & problem-solving',
        'Financial planning & strategy',
        'Budgeting & reporting',
        'Team supervision',
        'Compliance & risk management',
        'Decision-making & leadership',
        'Accounting software proficiency',
        'Credit risk assessment',
        'Financial statement analysis',
        'Reporting & recommendations',
        'Loan evaluation & approval',
        'Communication & decision-making',
        'Analytical thinking',
        'Financial management & oversight',
        'Accounting & reporting',
        'Budgeting & forecasting',
        'Internal controls & compliance',
        'Team management',
        'Analytical & problem-solving skills',
        'Cost analysis & control',
        'Budget monitoring',
        'Variance reporting',
        'Product costing & pricing',
        'Data analysis',
        'Cash flow management',
        'Investment & liquidity analysis',
        'Risk monitoring',
        'Banking & financial operations',
        'Reporting & documentation',
        'Analytical thinking',
        'Invoice processing & payment',
        'Vendor coordination',
        'Ledger entries & reconciliation',
        'MS Excel & accounting tools',
        'Time management',
        'Billing & collection management',
        'Customer account monitoring',
        'Ledger reconciliation',
        'Reporting & documentation',
        'MS Excel & accounting tools',
        'Communication & attention to detail',
        'Financial reporting & compliance',
        'Budget management',
        'Data analysis & forecasting',
        'Accounting software proficiency',
        'Problem-solving',
        'Portfolio & investment analysis',
        'Market research',
        'Financial modeling',
        'Reporting & recommendations',
        'Risk assessment',
        'Communication skills',
        'Risk identification & assessment',
        'Compliance monitoring',
        'Reporting & mitigation strategies',
        'Analytical thinking',
        'Documentation & attention to detail',
        'Problem-solving',
        'Regulatory compliance & audits',
        'Policy monitoring & enforcement',
        'Risk assessment',
        'Documentation & reporting',
        'Communication & analytical skills',
        'Attention to detail',
        'Loan assessment & approval',
        'Customer relationship management',
        'Financial statement analysis',
        'Risk assessment',
        'Communication & problem-solving',
        'Documentation',
        'Investment fund accounting',
        'NAV calculation & reporting',
        'Reconciliation & ledger management',
        'Compliance & auditing',
        'Analytical & detail-oriented',
        'Accounting software proficiency',
        'Invoice preparation & processing',
        'Accounts reconciliation',
        'Payment tracking & follow-ups',
        'Data entry & reporting',
        'Attention to detail',
        'Communication skills',
        'Cash & liquidity management',
        'Banking operations & reconciliation',
        'Risk monitoring & reporting',
        'Investment & fund management',
        'Documentation & compliance',
        'Analytical skills'
    ],
    'healthcare' => [
        'Medical knowledge and patient care skills',
        'Attention to detail and accuracy',
        'Communication and empathy',
        'Time management and multitasking',
        'Knowledge of healthcare software / EHR systems',
        'Critical thinking and problem-solving',
        'Teamwork and collaboration',
        'Patient assessment and monitoring',
        'Compliance with healthcare regulations',
        'Physical stamina and manual dexterity (for certain roles)',
        'Patient diagnosis & treatment',
        'Clinical procedures & examinations',
        'Medical record documentation',
        'Patient counseling',
        'Research & evidence-based practice',
        'Communication & empathy',
        'Patient care & monitoring',
        'Medication administration',
        'Vital signs monitoring',
        'Record keeping',
        'Emergency response',
        'Laboratory testing & analysis',
        'Sample collection & preparation',
        'Equipment operation',
        'Quality control & accuracy',
        'Documentation & reporting',
        'Safety compliance',
        'Medication dispensing & counseling',
        'Prescription review & verification',
        'Drug inventory management',
        'Patient education',
        'Compliance & documentation',
        'Attention to detail',
        'Oral examination & treatment',
        'Dental procedures & surgeries',
        'Patient education & counseling',
        'Sterilization & hygiene',
        'Record keeping',
        'Communication skills',
        'Imaging procedures (X-ray, CT, MRI)',
        'Patient positioning & safety',
        'Equipment operation',
        'Image quality control',
        'Documentation & reporting',
        'Communication & empathy',
        'Patient assessment & therapy planning',
        'Rehabilitation exercises',
        'Progress tracking & reporting',
        'Patient education',
        'Communication & motivation',
        'Problem-solving',
        'Activity & therapy planning',
        'Patient evaluation & progress monitoring',
        'Adaptive equipment instruction',
        'Collaboration with healthcare team',
        'Communication & empathy',
        'Documentation',
        'Sample collection & analysis',
        'Lab equipment handling',
        'Testing & quality control',
        'Record keeping',
        'Safety & compliance',
        'Attention to detail',
        'Maternal & neonatal care',
        'Labor & delivery assistance',
        'Patient counseling',
        'Health education',
        'Record keeping',
        'Communication & empathy',
        'Emergency response & patient care',
        'CPR & life support',
        'Triage & stabilization',
        'Patient transport & monitoring',
        'Communication & teamwork',
        'Documentation',
        'Nutritional assessment & planning',
        'Patient counseling',
        'Meal planning & monitoring',
        'Research & evidence-based recommendations',
        'Communication skills',
        'Record keeping',
        'Advanced patient care & diagnosis',
        'Treatment planning & prescription',
        'Patient counseling & education',
        'Collaboration with healthcare team',
        'Documentation & compliance',
        'Critical thinking',
        'Anesthesia administration',
        'Patient monitoring',
        'Surgical support & safety',
        'Emergency response',
        'Documentation & reporting',
        'Communication & teamwork',
        'Surgical procedures & planning',
        'Patient assessment & pre-op care',
        'Post-op monitoring',
        'Team coordination',
        'Documentation & compliance',
        'Problem-solving',
        'Patient intake & vital signs',
        'Clinical & administrative support',
        'Scheduling & documentation',
        'Assisting healthcare providers',
        'Communication & empathy',
        'Attention to detail',
        'Medical records management',
        'Data entry & coding',
        'Confidentiality & compliance',
        'Reporting & documentation',
        'Database management',
        'Attention to detail',
        'Speech & language assessment',
        'Therapy planning & delivery',
        'Progress monitoring & reporting',
        'Patient & family education',
        'Communication & empathy',
        'Record keeping',
        'Psychological assessment & testing',
        'Counseling & therapy',
        'Behavior analysis',
        'Report writing & documentation',
        'Communication & empathy',
        'Research & evaluation',
        'Patient care coordination',
        'Treatment plan monitoring',
        'Interdisciplinary collaboration',
        'Documentation & reporting',
        'Communication & problem-solving',
        'Patient advocacy'
    ],
    'human resources' => [
        'Recruitment and onboarding',
        'Employee relations and conflict resolution',
        'Payroll and benefits knowledge',
        'HR software proficiency (SAP, Workday, BambooHR, etc.)',
        'Communication and interpersonal skills',
        'Organizational skills',
        'Confidentiality and ethical judgment',
        'Training and development facilitation',
        'Performance management',
        'Policy development and compliance',
        'HR strategy & planning',
        'Staff management & supervision',
        'Recruitment & onboarding',
        'Employee relations',
        'Performance management',
        'Policy compliance & development',
        'Candidate sourcing & screening',
        'Interview coordination',
        'Applicant tracking systems (ATS)',
        'Communication & interpersonal skills',
        'Offer negotiation',
        'Record keeping',
        'Employee relations & support',
        'HR policy implementation',
        'Recruitment & onboarding',
        'Performance evaluation',
        'HRIS management',
        'Communication & problem-solving',
        'Training program design & delivery',
        'Needs assessment',
        'Employee engagement & facilitation',
        'Communication & presentation skills',
        'Record keeping & evaluation',
        'Instructional design knowledge',
        'Candidate sourcing & assessment',
        'Recruitment strategy',
        'Interviewing & evaluation',
        'Employer branding',
        'HR metrics & reporting',
        'Communication & negotiation',
        'Payroll & benefits administration',
        'Salary structuring & compliance',
        'Employee queries resolution',
        'HRIS & payroll systems',
        'Reporting & documentation',
        'Analytical skills',
        'Recruitment support',
        'Employee record management',
        'HR documentation & reporting',
        'Communication & coordination',
        'Scheduling interviews & onboarding',
        'Basic HR policies knowledge',
        'Employee support & conflict resolution',
        'Grievance handling',
        'Policy implementation',
        'Communication & negotiation',
        'HR reporting',
        'Compliance monitoring',
        'Strategic HR advisory',
        'Performance management & coaching',
        'Talent management',
        'Change management support',
        'Communication & stakeholder management',
        'Analytical & problem-solving skills',
        'Payroll processing & compliance',
        'Tax & statutory deductions',
        'HRIS & payroll systems',
        'Reporting & documentation',
        'Accuracy & attention to detail',
        'Employee support',
        'HR data analysis & reporting',
        'Metrics tracking (turnover, engagement, etc.)',
        'HRIS data management',
        'Forecasting & recommendations',
        'Analytical & problem-solving skills',
        'Communication',
        'HR advisory & strategy',
        'Policy review & compliance',
        'Recruitment & performance guidance',
        'Employee engagement strategies',
        'Communication & presentation',
        'Problem-solving',
        'Employee onboarding process',
        'Orientation & training facilitation',
        'Documentation & record keeping',
        'Communication & coordination',
        'HRIS usage',
        'Attention to detail'
    ],
    'information technology' => [
        'Programming languages (Python, Java, C#, etc.)',
        'Networking knowledge',
        'Systems and database management',
        'Cybersecurity awareness',
        'Cloud platforms (AWS, Azure, Google Cloud)',
        'Problem-solving and analytical thinking',
        'Software development lifecycle knowledge',
        'IT support and troubleshooting',
        'Project management',
        'Team collaboration'
    ],
    'manufacturing' => [
        'Production processes knowledge',
        'Quality control and inspection',
        'Equipment and machinery operation',
        'Safety and compliance awareness',
        'Technical and mechanical skills',
        'Problem-solving and troubleshooting',
        'Time management and efficiency',
        'Teamwork',
        'Inventory management',
        'Process improvement'
    ],
    'marketing' => [
        'Market research and analysis',
        'Social media management',
        'SEO and digital marketing',
        'Content creation and copywriting',
        'Branding and communication skills',
        'Creativity and innovation',
        'Analytics and reporting tools proficiency (Google Analytics, etc.)',
        'Project management',
        'Advertising and campaign management',
        'Customer engagement and relationship management',
        'Market research & analysis',
        'Campaign planning & execution',
        'Digital marketing tools (SEO, SEM)',
        'Content creation & branding',
        'Data analysis & reporting',
        'Communication & creativity',
        'Lead generation & prospecting',
        'Customer relationship management',
        'Negotiation & closing sales',
        'Product knowledge',
        'Reporting & target achievement',
        'Communication & presentation skills',
        'Brand strategy & positioning',
        'Market analysis & trend monitoring',
        'Campaign planning & execution',
        'Team coordination',
        'Communication & creativity',
        'Budget management',
        'Client relationship management',
        'Account planning & strategy',
        'Sales growth & retention',
        'Reporting & analysis',
        'Negotiation & communication',
        'Problem-solving',
        'Social media strategy & content creation',
        'Analytics & performance monitoring',
        'Engagement & community management',
        'Platform tools (Facebook, Instagram, LinkedIn)',
        'Creativity & communication',
        'Campaign execution',
        'Campaign coordination',
        'Event planning & execution',
        'Budgeting & vendor coordination',
        'Reporting & documentation',
        'Communication & teamwork',
        'Attention to detail',
        'Market research & lead generation',
        'Client acquisition & relationship building',
        'Proposal & presentation skills',
        'Negotiation & closing deals',
        'Reporting & analytics',
        'Communication & problem-solving',
        'Campaign planning & execution',
        'Media buying & placement',
        'Creativity & content development',
        'Reporting & analytics',
        'Communication & collaboration',
        'Market research',
        'SEO, SEM, PPC analysis',
        'Web & social media analytics',
        'Campaign performance tracking',
        'Data reporting & optimization',
        'Tools: Google Analytics, Ads Manager',
        'Communication & problem-solving',
        'Product lifecycle management',
        'Market research & analysis',
        'Strategy & roadmap planning',
        'Team coordination & collaboration',
        'Reporting & documentation',
        'Decision-making & problem-solving',
        'Team management & coaching',
        'Sales target planning & monitoring',
        'Client relationship management',
        'Reporting & analysis',
        'Problem-solving & communication',
        'Motivation & leadership',
        'Market research & trend analysis',
        'Data collection & reporting',
        'Campaign effectiveness evaluation',
        'Communication & presentation',
        'Problem-solving & analytical thinking',
        'Tools: Excel, BI software',
        'Promotion planning & execution',
        'Campaign monitoring & reporting',
        'Vendor & partner coordination',
        'Communication & marketing support',
        'Event participation & organization',
        'Creativity & problem-solving'
    ],
    'creative' => [
        'Adobe Creative Suite (Photoshop, Illustrator, InDesign)',
        'Visual design & layout',
        'Branding & typography',
        'Creativity & innovation',
        'Communication & presentation',
        'Time management & deadlines',
        'Video editing software (Premiere, Final Cut Pro, After Effects)',
        'Storyboarding & sequencing',
        'Audio & color correction',
        'Motion graphics basics',
        'Attention to detail',
        'Creativity & storytelling',
        'Writing & storytelling',
        'Photography & videography',
        'Social media content development',
        'SEO & engagement strategies',
        'Creativity & trend awareness',
        'Analytics & audience understanding',
        'Visual design leadership',
        'Creative strategy & concept development',
        'Team management',
        'Branding & style consistency',
        'Communication & collaboration',
        'Project management',
        'Digital & traditional illustration',
        'Drawing & sketching skills',
        'Creativity & imagination',
        'Adobe Illustrator / Procreate proficiency',
        'Attention to detail',
        'Visual storytelling',
        'Photography techniques & lighting',
        'Photo editing & retouching',
        'Composition & framing',
        'Camera & equipment handling',
        'Creativity & artistic vision',
        'Communication & client handling',
        '2D & 3D animation',
        'Motion graphics & effects',
        'Storyboarding & visual storytelling',
        'Software (After Effects, Blender, Maya)',
        'Creativity & attention to detail',
        'Collaboration & time management',
        'Writing & editing',
        'Creative thinking & storytelling',
        'SEO & digital marketing knowledge',
        'Research & brand voice consistency',
        'Communication skills',
        'Deadline management',
        'Wireframing & prototyping',
        'User research & testing',
        'Design tools (Figma, Sketch, Adobe XD)',
        'Information architecture & usability',
        'Creativity & problem-solving',
        'Communication & collaboration',
        'Creative vision & leadership',
        'Brand & campaign strategy',
        'Team management & mentorship',
        'Communication & presentation',
        'Project planning & execution',
        'Creative problem-solving',
        'Graphic & visual design',
        'Branding & digital assets creation',
        'Adobe Creative Suite proficiency',
        'Typography & color theory',
        'Attention to detail & creativity',
        'Collaboration & communication',
        'Web design & UI/UX principles',
        'HTML/CSS basics',
        'Responsive design',
        'Adobe XD / Figma / Photoshop',
        'Creativity & problem-solving',
        'Communication & client coordination',
        'Set & stage design',
        'Artistic direction',
        'Visual storytelling',
        'CAD / Sketching / Drafting',
        'Collaboration & coordination',
        'Creativity & time management',
        'Print & digital layout',
        'Typography & composition',
        'Adobe InDesign / Illustrator',
        'Attention to detail',
        'Creativity & aesthetics',
        'Communication & teamwork'
    ],
    'construction' => [
        'Project planning & execution',
        'Budgeting & cost management',
        'Team & contractor supervision',
        'Risk assessment & problem-solving',
        'Compliance with building codes & safety regulations',
        'Communication & leadership',
        'Construction site supervision',
        'Technical drawings interpretation',
        'Quality control & inspections',
        'Materials & resource management',
        'Problem-solving & troubleshooting',
        'Safety compliance',
        'Architectural design & drafting',
        'CAD software (AutoCAD, Revit, SketchUp)',
        'Building regulations & codes',
        'Creativity & spatial planning',
        'Project coordination & client communication',
        'Presentation & visualization skills',
        'Team supervision & task delegation',
        'Construction workflow management',
        'Safety compliance',
        'Quality assurance',
        'Communication & problem-solving',
        'Time management',
        'Project planning & scheduling',
        'Budget & resource allocation',
        'Team coordination & leadership',
        'Risk management & reporting',
        'Stakeholder communication',
        'Problem-solving & decision-making',
        'Cost estimation & budgeting',
        'Contract administration',
        'Materials & resource evaluation',
        'Reporting & documentation',
        'Analytical & numerical skills',
        'Negotiation & communication',
        'Construction site support',
        'Technical drawing interpretation',
        'Materials testing & inspection',
        'Equipment operation',
        'Reporting & documentation',
        'Safety compliance',
        'Structural analysis & design',
        'CAD & engineering software',
        'Compliance with building codes',
        'Material specification',
        'Problem-solving & technical calculations',
        'Collaboration with engineers & architects',
        'Safety policy implementation',
        'Risk assessment & hazard identification',
        'Training & monitoring staff',
        'Compliance with safety regulations',
        'Incident investigation & reporting',
        'Communication & emergency response',
        'Inspection & compliance checking',
        'Knowledge of building codes & regulations',
        'Report preparation',
        'Problem identification & recommendation',
        'Communication with stakeholders',
        'Attention to detail',
        'Team supervision & workflow management',
        'Quality control & compliance',
        'Safety & risk management',
        'Coordination with project managers',
        'Reporting & documentation',
        'Problem-solving',
        'On-site engineering support',
        'Technical problem-solving',
        'Equipment & materials supervision',
        'Compliance & quality checks',
        'Reporting & documentation',
        'Communication with teams',
        'Project planning & design implementation',
        'Technical calculations & analysis',
        'Team coordination & supervision',
        'Quality assurance & compliance',
        'Reporting & documentation',
        'Problem-solving',
        'On-site team supervision',
        'Workflow & task scheduling',
        'Safety & compliance enforcement',
        'Quality monitoring',
        'Communication & coordination',
        'Problem-solving',
        'Cost estimation & budgeting',
        'Material & labor calculation',
        'Risk assessment',
        'Reporting & documentation',
        'Analytical & numerical skills',
        'Vendor & contractor coordination'
    ],
    'hospitality' => [
        'Food preparation & cooking techniques',
        'Recipe development & menu planning',
        'Food safety & sanitation (HACCP)',
        'Time management & multitasking',
        'Teamwork & kitchen coordination',
        'Creativity & presentation',
        'Coffee preparation & brewing techniques',
        'Latte art & presentation',
        'Customer service & communication',
        'Inventory & supply management',
        'Food safety & hygiene',
        'Time management',
        'Food preparation & service',
        'Cash handling & POS operation',
        'Hygiene & cleanliness',
        'Teamwork & reliability',
        'Staff management & supervision',
        'Customer service excellence',
        'Inventory & supply management',
        'Budgeting & financial oversight',
        'Scheduling & workflow optimization',
        'Problem-solving & communication',
        'Food preparation & cooking',
        'Team supervision & coordination',
        'Inventory & supplies management',
        'Safety & hygiene compliance',
        'Problem-solving',
        'Time management',
        'POS operation & cash handling',
        'Accuracy & attention to detail',
        'Basic math & record keeping',
        'Teamwork',
        'Customer service & greeting guests',
        'Reservation & seating management',
        'Communication & interpersonal skills',
        'Problem-solving',
        'Multitasking & organization',
        'Professional appearance & demeanor',
        'Food & beverage service',
        'Menu knowledge & recommendations',
        'Time management & multitasking',
        'Hygiene & safety compliance',
        'Teamwork',
        'Customer service & guest relations',
        'Reservation & check-in/out process',
        'Communication & problem-solving',
        'Billing & record management',
        'Knowledge of local attractions & services',
        'Professionalism & organization',
        'Public speaking & communication',
        'Knowledge of culture, history, & local sites',
        'Customer service & engagement',
        'Time management & scheduling',
        'Problem-solving & safety awareness',
        'Group management & leadership',
        'Event planning & organization',
        'Vendor & supplier coordination',
        'Budget & logistics management',
        'Communication & negotiation',
        'Customer service & problem-solving',
        'Time management & multitasking'
    ],
    'retail' => [
        'Team management & staff supervision',
        'Sales target planning & achievement',
        'Inventory & stock control',
        'Customer service excellence',
        'Budgeting & financial oversight',
        'Visual merchandising & store presentation',
        'Problem-solving & decision-making',
        'Supporting store manager & team supervision',
        'Inventory & stock monitoring',
        'Customer service management',
        'Staff training & scheduling',
        'Reporting & record keeping',
        'Problem-solving & coordination',
        'Customer service & relationship building',
        'Product knowledge & promotion',
        'Upselling & cross-selling techniques',
        'Point-of-sale (POS) operation',
        'Sales reporting & target achievement',
        'Communication & interpersonal skills',
        'Product display & visual presentation',
        'Inventory monitoring & stock placement',
        'Sales trend analysis',
        'Creativity & design sense',
        'Coordination with store management',
        'Attention to detail',
        'Cash handling & POS operation',
        'Customer service & communication',
        'Accuracy & attention to detail',
        'Transaction recording & reporting',
        'Time management & multitasking',
        'Basic math skills',
        'Staff supervision & workflow management',
        'Customer service monitoring',
        'Sales target tracking',
        'Inventory & stock control',
        'Problem-solving & team coordination',
        'Training & mentoring staff',
        'Stock receiving & organization',
        'Inventory monitoring & reporting',
        'Shelving & product placement',
        'Accuracy & attention to detail',
        'Coordination with store & warehouse',
        'Time management',
        'Sales planning & reporting',
        'Customer account management',
        'Order processing & follow-up',
        'Communication & coordination',
        'Problem-solving & team support',
        'Data entry & record keeping',
        'Customer support & problem resolution',
        'Product knowledge & guidance',
        'Communication & interpersonal skills',
        'Complaint handling & conflict resolution',
        'POS & system usage',
        'Teamwork & reliability',
        'Client relationship management',
        'Sales strategy & negotiation',
        'Account planning & reporting',
        'Communication & presentation skills',
        'Problem-solving & decision-making',
        'Coordination with internal teams',
        'Customer assistance & service',
        'Product display & organization',
        'Inventory checks & restocking',
        'Communication & teamwork',
        'Basic sales knowledge',
        'Attention to detail'
    ],
    'transportation' => [
        'Safe driving & road safety knowledge',
        'Vehicle operation & basic maintenance',
        'Route planning & navigation',
        'Time management & punctuality',
        'Customer service & communication',
        'Adherence to traffic laws & regulations',
        'Vehicle fleet management',
        'Scheduling & route optimization',
        'Maintenance planning & monitoring',
        'Budget & cost management',
        'Team supervision & coordination',
        'Logistics & safety compliance',
        'Scheduling & dispatching vehicles',
        'Route planning & optimization',
        'Communication & coordination',
        'Record keeping & reporting',
        'Problem-solving & time management',
        'Customer & driver support',
        'Cargo handling & logistics',
        'Safety & compliance with aviation regulations',
        'Equipment operation (forklifts, conveyors)',
        'Inventory & shipment documentation',
        'Team coordination & communication',
        'Time management & reliability',
        'Vehicle & driver dispatch coordination',
        'Communication & problem-solving',
        'Record keeping & reporting',
        'Customer support & inquiries',
        'Route optimization & scheduling',
        'Attention to detail',
        'Vehicle inspection & quality checks',
        'Maintenance & safety compliance',
        'Reporting & documentation',
        'Mechanical knowledge',
        'Problem-solving & attention to detail',
        'Communication with drivers & management',
        'Team supervision & coordination',
        'Route & delivery schedule planning',
        'Fleet monitoring & maintenance oversight',
        'Reporting & record keeping',
        'Problem-solving & decision-making',
        'Customer service & communication'
    ],
    'law enforcement' => [
        'Law enforcement & crime prevention',
        'Public safety & community engagement',
        'Investigation & reporting',
        'Communication & interpersonal skills',
        'Decision-making & problem-solving',
        'Physical fitness & situational awareness',
        'Investigative techniques & evidence collection',
        'Case analysis & report writing',
        'Interview & interrogation skills',
        'Legal knowledge & compliance',
        'Problem-solving & critical thinking',
        'Communication & coordination',
        'Evidence collection & preservation',
        'Laboratory analysis & scientific testing',
        'Attention to detail & documentation',
        'Knowledge of forensic techniques & tools',
        'Critical thinking & problem-solving',
        'Communication & report writing',
        'Data analysis & pattern recognition',
        'Risk assessment & threat evaluation',
        'Reporting & documentation',
        'Problem-solving & decision-making',
        'Communication & coordination',
        'Knowledge of law enforcement procedures',
        'Security & inmate supervision',
        'Conflict resolution & crisis management',
        'Documentation & reporting',
        'Physical fitness & safety enforcement',
        'Communication & interpersonal skills',
        'Compliance with regulations & protocols',
        'Data gathering & analysis',
        'Risk assessment & reporting',
        'Problem-solving & strategic thinking',
        'Communication & coordination',
        'Discretion & confidentiality',
        'Knowledge of laws & regulations',
        'Leadership & management',
        'Strategic planning & decision-making',
        'Communication & public relations',
        'Law enforcement oversight',
        'Crisis management & problem-solving',
        'Policy & compliance enforcement',
        'Risk assessment & safety planning',
        'Community engagement & education',
        'Law enforcement & crime reduction strategies',
        'Communication & teamwork',
        'Reporting & documentation',
        'Problem-solving & initiative'
    ],
];

$jobTitleGroupCategoryMap = [
    '🗂️ Administrative / Office' => 'administrative',
    '☎️ Customer Service / BPO' => 'customer service',
    '🎓 Education' => 'education',
    '⚙️ Engineering' => 'engineering',
    '💻 Information Technology (IT)' => 'information technology',
    '💰 Finance / Accounting' => 'finance',
    '🏥 Healthcare / Medical' => 'healthcare',
    '👥 Human Resources (HR)' => 'human resources',
    '🏭 Manufacturing / Production' => 'manufacturing',
    '🚚 Logistics / Warehouse / Supply Chain' => 'logistics',
    '📈 Marketing / Sales' => 'marketing',
    '🎨 Creative / Media / Design' => 'creative',
    '🏗️ Construction / Infrastructure' => 'construction',
    '🍽️ Food / Hospitality / Tourism (Fast-Food Included)' => 'hospitality',
    '🛒 Retail / Sales Operations' => 'retail',
    '🚗 Transportation' => 'transportation',
    '👮 Law Enforcement / Criminology' => 'law enforcement',
    '🛡️ Security Services' => 'security',
    '🔧 Skilled / Technical (TESDA)' => 'technical',
    '🌾 Agriculture / Fisheries' => 'agriculture',
    '🌐 Freelance / Online / Remote' => 'freelance',
    '⚖️ Legal / Government / Public Service' => 'legal',
    '✈️ Maritime / Aviation / Transport Specialized' => 'aviation',
    '🔬 Science / Research / Environment' => 'science',
    '🎭 Arts / Entertainment / Culture' => 'arts',
    '✝️ Religion / NGO / Development / Cooperative' => 'ngo',
    '🧩 Special / Rare Jobs' => 'special',
    '🔌 Utilities / Public Services' => 'utilities',
    '📡 Telecommunications' => 'telecommunications',
    '⛏️ Mining / Geology' => 'mining',
    '🛢️ Oil / Gas / Energy' => 'oil gas',
    '⚗️ Chemical / Industrial' => 'chemical',
    '🩺 Allied Health / Special Education / Therapy' => 'allied health',
    '🏋️ Sports / Fitness / Recreation' => 'sports',
    '👗 Fashion / Apparel / Beauty' => 'fashion',
    '🍔 Food Service / Fast Food / QSR' => 'food service',
    '🏡 Home / Personal Services' => 'personal services',
    '🏦 Insurance / Risk / Banking' => 'banking',
    '💼 Micro Jobs / Informal / Daily Wage Jobs' => 'micro jobs',
    '🏠 Real Estate / Property' => 'real estate',
    '📊 Entrepreneurship / Business / Corporate' => 'business',
];
$jobTitleToCategoryKey = [];
$jobTitleToCategoryOption = []; // Maps job title directly to category option value
foreach ($jobTitleGroups as $groupLabel => $titles) {
    $categoryKey = $jobTitleGroupCategoryMap[$groupLabel] ?? '';
    // Extract category option value from group label (remove emoji and get the text)
    $categoryOptionValue = preg_replace('/^[^\w\s]*\s*/', '', $groupLabel); // Remove emoji prefix
    foreach ($titles as $title) {
        $jobTitleToCategoryKey[$title] = $categoryKey;
        $jobTitleToCategoryOption[$title] = $categoryOptionValue;
    }
}
$categoryDefaultDescriptions = [
    'administrative' => 'Responsible for providing administrative and clerical support to ensure smooth office operations. Handles documentation, data entry, scheduling, email correspondence, and coordination with internal teams.',
    'customer service' => 'Handles customer inquiries, resolves issues, provides product or service information, and ensures customer satisfaction through effective communication.',
    'education' => 'Plans and delivers lessons, manages classroom activities, evaluates student performance, and supports academic development.',
    'engineering' => 'Designs, analyzes, and implements engineering solutions, manages projects, ensures safety compliance, and improves systems or processes.',
    'finance' => 'Manages financial records, prepares reports, ensures compliance with accounting standards, and supports audits.',
    'healthcare' => 'Provides patient care, monitors vital signs, administers medications, and supports doctors and healthcare teams.',
    'human resources' => 'Supports recruitment, maintains employee records, prepares HR documents, and assists in HR operations.',
    'information technology' => 'Designs, develops, and maintains applications and websites using programming languages and frameworks.',
    'manufacturing' => 'Operates machinery, follows safety procedures, and supports production processes.',
    'marketing' => 'Plans and executes marketing activities, conducts research, and supports campaigns.'
];
$jobTitleDescriptionOverrides = [
    'Administrative Assistant' => 'Responsible for providing administrative and clerical support to ensure smooth office operations. Handles documentation, data entry, scheduling, email correspondence, and coordination with internal teams.',
    'Office Administrator' => 'Oversees daily office operations, manages office supplies, coordinates administrative tasks, and ensures smooth workflow.',
    'Executive Assistant' => 'Provides high-level support to executives, manages schedules, organizes meetings, and handles confidential communications.',
    'Administrative Coordinator' => 'Coordinates office activities, assists in planning, monitors deadlines, and supports team communication.',
    'Data Entry Clerk' => 'Inputs data accurately into databases or systems, maintains records, and ensures data integrity.',
    'Office Manager' => 'Supervises administrative staff, ensures office efficiency, manages budgets, and implements policies.',
    'Receptionist' => 'Greets visitors, handles phone calls, manages appointments, and provides general administrative support.',
    'Personal Assistant' => 'Assists a specific individual with personal and professional tasks, scheduling, and correspondence.',
    'Administrative Officer' => 'Manages administrative processes, ensures compliance with company policies, and coordinates internal operations.',
    'Records Clerk' => 'Maintains and organizes records, archives documents, and ensures easy retrieval of information.',
    'Operations Assistant' => 'Supports daily operations, coordinates tasks across departments, and helps streamline workflows.',
    'Secretary' => 'Performs clerical duties, manages schedules, prepares documents, and supports executives or departments.',
    'Front Desk Officer' => 'Handles front office operations, receives guests, manages appointments, and assists with inquiries.',
    'Executive Secretary' => 'Provides high-level administrative support, manages confidential information, and coordinates executive activities.',
    'Office Clerk' => 'Performs general office duties such as filing, record-keeping, and handling correspondence.',
    'Filing Clerk' => 'Organizes, sorts, and maintains physical or electronic files for easy access and retrieval.',
    'Scheduling Coordinator' => 'Plans and coordinates appointments, meetings, and events for teams or executives.',
    'Office Services Manager' => 'Oversees office support services like maintenance, mail distribution, and vendor management.',
    'Documentation Specialist' => 'Prepares, reviews, and manages documents, ensuring accuracy and compliance with regulations.',
    'Office Support Specialist' => 'Provides general administrative assistance, troubleshooting, and operational support.',
    'Office Supervisor' => 'Supervises administrative staff, monitors office workflow, and ensures efficiency and compliance.',
    'Clerk' => 'Performs accurate data entry, maintains records, files documents, and supports office operations using basic computer systems.',
    'Encoder' => 'Performs accurate data entry, maintains records, files documents, and supports office operations using basic computer systems.',
    'Customer Service Representative' => 'Handles customer inquiries, resolves complaints, provides product/service information, and ensures customer satisfaction.',
    'Call Center Agent' => 'Manages inbound/outbound calls, assists customers with queries, and follows company scripts to provide support.',
    'Client Support Specialist' => 'Offers specialized support to clients, addresses technical or service issues, and maintains client relationships.',
    'Help Desk Associate' => 'Provides technical support for software/hardware issues, troubleshoots problems, and documents solutions.',
    'Customer Care Coordinator' => 'Coordinates customer service operations, monitors response quality, and supports client satisfaction initiatives.',
    'Technical Support Representative' => 'Assists customers with technical issues, guides troubleshooting steps, and escalates complex problems to experts.',
    'Service Desk Analyst' => 'Monitors service requests, resolves IT-related issues, and ensures timely support to users.',
    'Account Support Specialist' => 'Supports client accounts, manages service requests, and ensures account satisfaction and compliance.',
    'Call Center Supervisor' => 'Leads a team of agents, monitors performance, conducts training, and ensures targets are met.',
    'Customer Experience Associate' => 'Enhances customer interactions, gathers feedback, and recommends improvements to services or processes.',
    'Contact Center Trainer' => 'Conducts training sessions for call center staff, develops learning materials, and evaluates performance.',
    'Chat Support Agent' => 'Provides real-time assistance to customers through online chat platforms, resolving inquiries efficiently.',
    'Email Support Specialist' => 'Responds to customer emails, resolves issues, and ensures timely communication with clients.',
    'Escalation Officer' => 'Handles complex or high-priority customer issues escalated from frontline staff, providing resolutions promptly.',
    'QA Analyst (Customer Service)' => 'Monitors customer interactions, evaluates service quality, and provides feedback for process improvement.',
    'Customer Retention Specialist' => 'Implements strategies to retain customers, resolves dissatisfaction, and promotes loyalty programs.',
    'Virtual Customer Service Associate' => 'Provides remote support via phone, email, or chat, assisting clients from a virtual environment.',
    'Inside Sales / Customer Support' => 'Manages inbound customer inquiries while also promoting and selling products/services over the phone or online.',
    'Team Lead – Customer Support' => 'Supervises customer support team, manages schedules, ensures service KPIs are met, and mentors staff.',
    'Teacher' => 'Plans and delivers lessons, assesses student performance, and fosters a positive learning environment.',
    'School Counselor' => 'Provides guidance, support, and counseling to students regarding academic, personal, and career matters.',
    'Academic Coordinator' => 'Oversees curriculum implementation, coordinates academic programs, and ensures educational standards are met.',
    'Tutor' => 'Offers one-on-one or small group instruction to help students improve understanding and academic performance.',
    'Principal' => 'Manages overall school operations, supervises staff, ensures compliance with policies, and leads academic initiatives.',
    'Librarian' => 'Manages library resources, assists students and staff in research, and organizes information systems.',
    'Special Education Teacher' => 'Educates students with special needs, develops individualized learning plans, and supports their academic and social development.',
    'Curriculum Developer' => 'Designs, updates, and evaluates curriculum content to ensure effective learning outcomes.',
    'Education Program Manager' => 'Plans, implements, and evaluates educational programs, coordinating with teachers and stakeholders.',
    'Lecturer' => 'Delivers lectures in colleges or universities, develops course materials, and assesses student performance.',
    'College Instructor' => 'Teaches specialized subjects to college students, prepares lesson plans, and evaluates student progress.',
    'Preschool Teacher' => 'Provides early childhood education, develops social and cognitive skills, and creates a safe learning environment.',
    'Teaching Assistant' => 'Supports teachers in classroom activities, helps students with tasks, and assists with administrative duties.',
    'Instructional Designer' => 'Designs educational materials and courses for effective learning, often using technology-based solutions.',
    'Learning Facilitator' => 'Guides learning activities, encourages student participation, and ensures engagement in educational programs.',
    'Education Consultant' => 'Advises schools or institutions on curriculum design, teaching strategies, and program development.',
    'Homeroom Teacher' => 'Manages a specific class, monitors student progress, and communicates with parents regarding academic and behavioral issues.',
    'School Administrator' => 'Handles school operations, manages staff, coordinates programs, and ensures compliance with educational standards.',
    'Guidance Counselor' => 'Supports student development through academic advising, career counseling, and personal guidance.',
    'Academic Adviser' => 'Provides advice to students on course selection, career paths, and academic progression.',
    'Instructor' => 'Plans and delivers lessons, manages classroom activities, evaluates student performance, and supports academic development.',
    'Professor' => 'Plans and delivers lessons, manages classroom activities, evaluates student performance, and supports academic development.',
    'Trainer' => 'Designs and delivers training programs, coordinates academic activities, and ensures learning objectives are met.',
    'Civil Engineer' => 'Designs, constructs, and maintains infrastructure projects such as roads, bridges, and buildings, ensuring safety and compliance.',
    'Mechanical Engineer' => 'Develops, tests, and oversees the production of mechanical systems and machinery, ensuring functionality and efficiency.',
    'Electrical Engineer' => 'Designs, develops, and maintains electrical systems, circuits, and equipment for industrial or commercial use.',
    'Project Engineer' => 'Manages engineering projects from planning to completion, coordinating teams and ensuring timely delivery.',
    'Structural Engineer' => 'Analyzes and designs structures to ensure they can withstand forces and loads safely.',
    'Chemical Engineer' => 'Develops processes for chemical production, ensures safety, and optimizes efficiency in manufacturing or lab operations.',
    'Industrial Engineer' => 'Improves production processes, increases efficiency, reduces waste, and ensures smooth workflow in manufacturing or services.',
    'Process Engineer' => 'Designs and optimizes industrial processes, ensuring efficiency, safety, and compliance with standards.',
    'Quality Engineer' => 'Monitors and improves product quality, conducts inspections, and implements quality control procedures.',
    'Design Engineer' => 'Creates detailed designs for products, machinery, or systems using CAD software and engineering principles.',
    'Maintenance Engineer' => 'Plans and performs maintenance on equipment and machinery to prevent breakdowns and ensure reliability.',
    'Field Engineer' => 'Provides on-site engineering support, supervises construction, and resolves technical issues in the field.',
    'Systems Engineer' => 'Designs, integrates, and manages complex engineering systems, ensuring they meet technical and functional requirements.',
    'Engineering Technician' => 'Assists engineers in testing, measurement, and implementation of technical solutions.',
    'Automation Engineer' => 'Develops automated systems, robotics, and control processes to optimize manufacturing or operations.',
    'Product Design Engineer' => 'Designs and develops new products, focusing on functionality, aesthetics, and manufacturability.',
    'Control Systems Engineer' => 'Designs and manages control systems for machinery, processes, and industrial operations.',
    'Environmental Engineer' => 'Develops solutions to environmental problems, including pollution control, waste management, and sustainability initiatives.',
    'Safety Engineer' => 'Ensures workplace and operational safety by identifying hazards, implementing safety protocols, and conducting inspections.',
    'Reliability Engineer' => 'Analyzes systems or equipment to improve reliability, reduce failures, and optimize performance.',
    'Software Engineer' => 'Designs, codes, tests, and maintains software applications and systems.',
    'Site Engineer' => 'Supervises construction or engineering projects on-site, ensures compliance with plans, coordinates teams, and manages documentation.',
    'Accountant' => 'Prepares and examines financial records, ensures accuracy, and manages budgets and financial reporting.',
    'Financial Analyst' => 'Analyzes financial data, forecasts performance, and provides insights for investment or business decisions.',
    'Bookkeeper' => 'Maintains daily financial records, records transactions, and reconciles accounts.',
    'Payroll Officer' => 'Manages employee payroll, calculates salaries, deductions, and ensures timely payments.',
    'Tax Specialist' => 'Prepares tax returns, ensures compliance with tax laws, and advises on tax planning.',
    'Budget Analyst' => 'Develops, analyzes, and monitors budgets, ensuring efficient allocation of resources.',
    'Auditor' => 'Reviews financial records, ensures compliance with accounting standards, and detects discrepancies.',
    'Finance Manager' => 'Oversees financial operations, plans budgets, manages investments, and ensures financial health of an organization.',
    'Credit Analyst' => 'Evaluates creditworthiness of clients or companies, assesses financial risk, and provides recommendations.',
    'Controller' => 'Manages accounting operations, financial reporting, and ensures compliance with policies and regulations.',
    'Cost Accountant' => 'Analyzes production costs, prepares cost reports, and helps management in pricing and cost control decisions.',
    'Treasury Analyst' => 'Manages cash flow, investments, and financial risk to optimize an organization\'s liquidity.',
    'Accounts Payable Clerk' => 'Processes invoices, ensures timely payments, and maintains vendor records.',
    'Accounts Receivable Clerk' => 'Manages incoming payments, issues invoices, and reconciles customer accounts.',
    'Finance Officer' => 'Oversees financial transactions, monitors budgets, and ensures accurate reporting.',
    'Investment Analyst' => 'Evaluates investment opportunities, conducts research, and advises on portfolio management.',
    'Risk Officer' => 'Identifies financial risks, develops mitigation strategies, and ensures compliance with risk policies.',
    'Compliance Officer – Finance' => 'Ensures financial operations comply with laws, regulations, and internal policies.',
    'Loan Officer' => 'Assesses loan applications, evaluates credit risk, and recommends approvals or rejections.',
    'Fund Accountant' => 'Manages investment fund accounting, tracks transactions, and prepares financial reports.',
    'Billing Officer' => 'Prepares and issues invoices, reconciles accounts, and monitors outstanding payments.',
    'Treasury Officer' => 'Manages company funds, monitors cash flow, and optimizes investment and financing strategies.',
    'Doctor' => 'A doctor, also called a physician, diagnoses, treats, and manages illnesses, injuries, and medical conditions in patients. They examine patients, order and interpret diagnostic tests, prescribe medications, and create treatment plans. Doctors also educate patients on disease prevention, healthy lifestyles, and ongoing care. They may specialize in areas such as internal medicine, pediatrics, surgery, cardiology, or other fields.',
    'Nurse' => 'Provides patient care, administers medications, monitors health conditions, and educates patients on health management.',
    'Medical Technologist' => 'Performs laboratory tests, analyzes specimens, and reports results to assist in diagnosis and treatment.',
    'Physician' => 'Diagnoses and treats illnesses, prescribes medications, and provides medical care to patients.',
    'Pharmacist' => 'Prepares and dispenses medications, advises on proper usage, and ensures patient safety.',
    'Dentist' => 'Diagnoses and treats dental issues, performs procedures, and promotes oral health.',
    'Radiologic Technologist' => 'Operates imaging equipment (X-ray, CT scan), assists in diagnosis, and ensures patient safety during procedures.',
    'Physical Therapist' => 'Assesses and treats physical impairments, develops rehabilitation plans, and improves patient mobility.',
    'Occupational Therapist' => 'Helps patients regain independence through therapeutic activities and adaptive strategies.',
    'Laboratory Technician' => 'Supports medical testing, collects specimens, performs tests, and ensures accurate lab results.',
    'Midwife' => 'Provides care during pregnancy, childbirth, and postpartum, ensuring safe delivery and maternal health.',
    'Paramedic' => 'Provides emergency medical care, stabilizes patients, and transports them to healthcare facilities.',
    'Dietitian' => 'Plans and advises on nutrition, develops meal plans, and supports health management.',
    'Nurse Practitioner' => 'Provides advanced nursing care, diagnoses illnesses, prescribes treatment, and manages patient care.',
    'Anesthesiologist' => 'Administers anesthesia during surgeries, monitors patient vital signs, and ensures pain management.',
    'Surgeon' => 'Performs surgical procedures, diagnoses conditions requiring surgery, and ensures patient recovery.',
    'Medical Assistant' => 'Assists physicians and nurses, performs basic medical procedures, and manages patient records.',
    'Health Information Technician' => 'Manages medical records, ensures data accuracy, and maintains patient confidentiality.',
    'Speech Therapist' => 'Diagnoses and treats speech, language, and communication disorders.',
    'Special Educator' => 'Designs and implements educational programs for students with special needs.',
    'Rehabilitation Specialist' => 'Assists patients in regaining independence and improving quality of life through therapy programs.',
    'Psychologist' => 'Evaluates, diagnoses, and treats mental health issues and provides counseling or therapy.',
    'Audiologist' => 'Diagnoses and treats hearing and balance disorders, fitting hearing aids if needed.',
    'Orthotist' => 'Designs, fits, and maintains orthopedic braces or supports for patients.',
    'Prosthetist' => 'Designs, fits, and maintains artificial limbs for patients.',
    'Behavioral Therapist' => 'Develops behavior modification plans and therapeutic interventions for patients with behavioral disorders.',
    'Therapy Assistant' => 'Supports therapists in administering therapy sessions and monitoring patient progress.',
    'Learning Support Officer' => 'Assists students with learning difficulties, provides educational support, and monitors academic progress.',
    'Care Coordinator' => 'Organizes patient care, communicates with healthcare teams, and ensures comprehensive treatment plans.',
    'Emergency Medical Technician' => 'Responds to emergencies, provides pre-hospital care, and transports patients safely.',
    'Clinical Coordinator' => 'Oversees clinical operations, manages staff, ensures compliance with protocols, and maintains patient care standards.',
    'Caregiver' => 'Provides daily living assistance, emotional support, and care to elderly or disabled individuals.',
    'HR Manager' => 'Oversees all HR functions, develops policies, manages recruitment, employee relations, and ensures compliance with labor laws.',
    'Recruitment Specialist' => 'Sources, screens, and hires candidates, manages job postings, and coordinates interviews.',
    'HR Generalist' => 'Handles day-to-day HR activities, including employee relations, benefits administration, and policy implementation.',
    'Training Coordinator' => 'Organizes and conducts employee training programs, evaluates effectiveness, and maintains training records.',
    'Talent Acquisition Officer' => 'Develops hiring strategies, recruits top talent, and builds a pipeline of potential candidates.',
    'Compensation & Benefits Specialist' => 'Designs and manages employee compensation packages, benefits programs, and ensures market competitiveness.',
    'HR Assistant' => 'Provides administrative support to the HR department, maintains records, and assists with recruitment and onboarding.',
    'Employee Relations Officer' => 'Addresses employee concerns, mediates disputes, and ensures positive workplace relationships.',
    'HR Business Partner' => 'Collaborates with management to align HR strategies with business goals, focusing on performance and development.',
    'Learning & Development Officer' => 'Plans and implements employee development programs to enhance skills and performance.',
    'HR Coordinator' => 'Supports HR activities, maintains employee records, and coordinates HR projects and events.',
    'Payroll Specialist' => 'Processes payroll, ensures accurate salary computation, deductions, and compliance with labor regulations.',
    'HR Analyst' => 'Collects and analyzes HR data, reports on trends, and provides insights to improve workforce management.',
    'Recruitment Coordinator' => 'Schedules interviews, communicates with candidates, and supports recruitment logistics.',
    'HR Consultant' => 'Provides expert advice on HR policies, organizational development, and employee management strategies.',
    'Onboarding Specialist' => 'Facilitates new employee orientation, ensures smooth integration, and provides necessary resources.',
    'HR Officer' => 'Executes HR programs, maintains compliance, and supports employee engagement and administration.',
    'HR Administrator' => 'Manages HR records, supports departmental operations, and ensures proper documentation and filing.',
    'Software Developer' => 'Designs, codes, tests, and maintains software applications to meet user requirements.',
    'Network Administrator' => 'Manages and maintains computer networks, ensuring connectivity, security, and efficiency.',
    'IT Support Specialist' => 'Provides technical assistance to users, troubleshoots hardware/software issues, and resolves IT problems.',
    'Web Developer' => 'Builds and maintains websites, ensuring functionality, responsiveness, and user experience.',
    'Systems Analyst' => 'Analyzes IT systems, identifies improvements, and recommends solutions to enhance performance.',
    'Database Administrator' => 'Manages databases, ensures data integrity, performance optimization, and security.',
    'Cybersecurity Analyst' => 'Protects IT systems and networks from cyber threats, monitors security incidents, and implements safeguards.',
    'Cloud Engineer' => 'Designs, implements, and manages cloud-based infrastructure and services for organizations.',
    'IT Manager' => 'Oversees IT department operations, manages staff, plans projects, and ensures technology aligns with business goals.',
    'Technical Lead' => 'Leads development teams, provides technical guidance, and ensures project deliverables meet quality standards.',
    'Application Developer' => 'Designs and builds software applications for desktop, web, or mobile platforms.',
    'DevOps Engineer' => 'Bridges development and operations, automates deployment pipelines, and ensures smooth software delivery.',
    'Mobile App Developer' => 'Develops and maintains mobile applications for iOS and Android platforms.',
    'Data Engineer' => 'Designs and manages data pipelines, databases, and ETL processes for data-driven systems.',
    'Network Security Engineer' => 'Implements and maintains security measures for networks, firewalls, and intrusion prevention systems.',
    'IT Project Manager' => 'Plans, executes, and monitors IT projects, ensuring deadlines, budgets, and objectives are met.',
    'UX/UI Developer' => 'Designs and develops user interfaces, improving usability and enhancing user experience.',
    'Front-End Developer' => 'Develops the client-side of websites and applications, ensuring interactive and responsive design.',
    'Back-End Developer' => 'Builds server-side logic, manages databases, and ensures smooth data flow between systems.',
    'IT Infrastructure Engineer' => 'Designs, implements, and maintains IT hardware, servers, and networks to support business operations.',
    'IT Consultant' => 'Provides expert advice on IT strategy, system integration, and technology solutions for businesses.',
    'IT Auditor' => 'Reviews IT systems and processes to ensure compliance, security, and operational efficiency.',
    'Software Developer / Programmer' => 'Designs, develops, and maintains applications and websites using programming languages and frameworks.',
    'Web Developer (Front-end / Back-end / Full Stack)' => 'Designs, develops, and maintains applications and websites using programming languages and frameworks.',
    'Mobile App Developer' => 'Designs, develops, and maintains applications and websites using programming languages and frameworks.',
    'System Analyst' => 'Analyzes system requirements, prepares documentation, and recommends IT solutions.',
    'IT Support / Helpdesk' => 'Provides technical assistance, troubleshoots hardware and software issues, and supports users through calls, chats, or emails.',
    'QA Tester' => 'Tests software applications, identifies bugs, and ensures product quality.',
    'Cybersecurity Specialist' => 'Monitors security systems, protects data, assesses risks, and prevents cyber threats.',
    'Production Supervisor' => 'Oversees daily production operations, ensures targets are met, monitors quality, and manages staff.',
    'Machine Operator' => 'Operates machinery in manufacturing processes, ensures proper functioning, and follows safety guidelines.',
    'Quality Control Inspector' => 'Examines products and materials to ensure they meet quality standards and specifications.',
    'Plant Manager' => 'Manages overall plant operations, production schedules, staffing, and ensures efficiency and safety compliance.',
    'Production Planner' => 'Plans production schedules, coordinates materials and resources, and ensures timely product delivery.',
    'Assembler' => 'Assembles components or products according to specifications and maintains quality standards.',
    'Factory Worker' => 'Performs general manufacturing tasks such as assembly, packaging, or machine operation.',
    'Manufacturing Engineer' => 'Designs and optimizes manufacturing processes, improving efficiency and reducing waste.',
    'Line Supervisor' => 'Oversees production line staff, ensures workflow efficiency, and addresses operational issues.',
    'Shift Supervisor' => 'Manages operations during a specific shift, monitors performance, and ensures production targets are achieved.',
    'Inventory Controller' => 'Manages inventory levels, monitors stock movements, and ensures materials are available for production.',
    'Process Operator' => 'Controls and monitors production processes, ensures safety and efficiency of operations.',
    'Production Technician' => 'Performs technical tasks in manufacturing, including machine setup, testing, and troubleshooting.',
    'Packaging Operator' => 'Prepares and packages products for shipment, ensuring quality and proper labeling.',
    'Production Scheduler' => 'Plans production timelines, coordinates resources, and ensures deadlines are met.',
    'Operations Supervisor' => 'Oversees production and manufacturing operations, monitors workflow, and implements efficiency improvements.',
    'Plant Technician' => 'Maintains machinery, performs troubleshooting, and ensures production equipment operates smoothly.',
    'Warehouse Staff' => 'Manages inventory, organizes stocks, and supports logistics operations.',
    'Marketing Specialist' => 'Develops and implements marketing strategies, analyzes market trends, and promotes products or services.',
    'Sales Executive' => 'Identifies potential clients, presents products or services, and closes sales deals to meet targets.',
    'Brand Manager' => 'Manages brand identity, develops marketing campaigns, and ensures consistent messaging across all channels.',
    'Account Manager' => 'Maintains relationships with clients, manages accounts, and ensures client satisfaction and retention.',
    'Social Media Manager' => 'Plans, creates, and manages social media content, campaigns, and engagement strategies.',
    'Marketing Coordinator' => 'Supports marketing activities, organizes campaigns, and coordinates events and promotions.',
    'Business Development Officer' => 'Identifies business opportunities, builds partnerships, and drives revenue growth.',
    'Advertising Specialist' => 'Plans and executes advertising campaigns across various media platforms to reach target audiences.',
    'Digital Marketing Analyst' => 'Monitors online marketing performance, analyzes data, and provides recommendations for optimization.',
    'Product Manager' => 'Oversees product lifecycle, coordinates development, and ensures product meets market needs.',
    'Sales Supervisor' => 'Leads sales teams, monitors performance, provides coaching, and ensures sales targets are met.',
    'Key Account Manager' => 'Manages relationships with high-value clients, ensures service delivery, and drives account growth.',
    'Territory Sales Manager' => 'Oversees sales activities in a specific region, develops strategies, and manages the sales team.',
    'Marketing Analyst' => 'Conducts market research, analyzes data, and provides insights for marketing strategies.',
    'Event Marketing Coordinator' => 'Plans and executes events, trade shows, and promotional activities to boost brand awareness.',
    'Promotions Officer' => 'Implements promotional campaigns, manages advertising materials, and tracks campaign effectiveness.',
    'Content Strategist' => 'Plans and produces content to support marketing goals and engagement.',
    'SEO Specialist' => 'Optimizes website content for search engines to increase visibility.',
    'Market Research Analyst' => 'Analyzes market trends and consumer behavior to guide marketing decisions.',
    'Product Marketing Specialist' => 'Promotes products and develops strategies to drive sales.',
    'Digital Marketing Coordinator' => 'Executes online marketing campaigns across various digital channels.',
    'Graphic Designer' => 'Creates visual content for digital and print media, including logos, brochures, advertisements, and social media graphics.',
    'Video Editor' => 'Edits raw video footage, adds effects and transitions, and produces polished video content for various platforms.',
    'Content Creator' => 'Develops engaging content such as blogs, videos, social media posts, and other multimedia to attract audiences.',
    'Art Director' => 'Oversees the visual style and creative direction of projects, including advertising, publications, and media campaigns.',
    'Illustrator' => 'Produces illustrations, drawings, or digital art for books, advertisements, websites, or other media.',
    'Photographer' => 'Captures and edits images for commercial, artistic, or journalistic purposes.',
    'Animator' => 'Creates 2D or 3D animations for film, television, games, or online media.',
    'Copywriter' => 'Writes compelling text for advertisements, marketing materials, websites, and social media campaigns.',
    'UX/UI Designer' => 'Designs user interfaces and enhances user experience for websites, apps, and digital products.',
    'Creative Director' => 'Leads creative teams, develops concepts, and ensures overall consistency and quality of creative projects.',
    'Visual Designer' => 'Designs visually appealing graphics, interfaces, and marketing materials, focusing on aesthetics and brand alignment.',
    'Motion Graphics Designer' => 'Creates animated graphics and visual effects for video, presentations, or digital media.',
    'Web Designer' => 'Designs website layouts, graphics, and user interfaces, ensuring a visually engaging and functional site.',
    'Production Designer' => 'Designs sets, environments, and props for film, theater, or media productions.',
    'Layout Artist' => 'Arranges visual elements and typography for publications, advertisements, and digital media.',
    'Marketing Communications Officer' => 'Manages communications and PR strategies to enhance brand image.',
    'Marketing Officer' => 'Plans and executes marketing activities, conducts research, and supports campaigns.',
    'Digital Marketer' => 'Manages online marketing strategies including SEO, social media, and analytics.',
    'Social Media Manager' => 'Plans content, manages engagement, and builds online brand presence.',
    'Sales Representative' => 'Promotes products or services, communicates with customers, and supports sales growth.',
    'Account Executive' => 'Promotes products or services, communicates with customers, and supports sales growth.',
    'Operations Manager' => 'Oversees daily operations, streamlines processes, and coordinates teams to meet business goals.',
    'Project Manager' => 'Plans and manages projects, tracks progress, coordinates teams, and ensures timely delivery.',
    'Department Manager' => 'Leads a department, monitors performance, and ensures team objectives are achieved.',
    'Team Leader / Supervisor' => 'Guides team members, assigns tasks, and ensures workflow efficiency and quality.',
    'Operations Staff' => 'Supports operational tasks, coordinates resources, and assists in process execution.',
    'Logistics Officer' => 'Supports logistics operations, monitors shipments, and ensures compliance with company policies.',
    'Stock Controller' => 'Maintains accurate stock levels, monitors inventory movement, and reconciles discrepancies.',
    'Delivery Coordinator' => 'Schedules deliveries, communicates with drivers, and tracks shipments until delivery.',
    'Supply Officer' => 'Manages inventory of supplies, coordinates procurement, and ensures materials are available for operations.',
    'Logistics Manager' => 'Oversees end-to-end logistics operations, manages teams, and optimizes supply chain efficiency.',
    'Purchasing Officer' => 'Procures materials, negotiates with suppliers, and manages purchase orders.',
    'Supply Chain Manager' => 'Oversees supply chain activities, optimizes logistics, and manages vendor relationships.',
    'Driver' => 'Operates vehicles to transport passengers or goods safely, follows routes, and maintains vehicle condition.',
    'Delivery Rider' => 'Delivers packages, documents, or food to designated locations on time and in good condition.',
    'Fleet Manager' => 'Oversees a fleet of vehicles, schedules maintenance, manages drivers, and ensures operational efficiency.',
    'Transport Coordinator' => 'Plans and organizes transportation schedules, routes, and logistics for goods or personnel.',
    'Logistics Driver' => 'Transports goods between locations, ensures timely delivery, and maintains vehicle safety standards.',
    'Bus Driver' => 'Operates buses for public or private transport, ensures passenger safety, and follows schedules.',
    'Taxi Driver' => 'Transports passengers to their destinations safely and efficiently while managing fares.',
    'Air Cargo Handler' => 'Loads, unloads, and organizes cargo in airports, ensuring proper handling and documentation.',
    'Dispatch Officer' => 'Coordinates transportation schedules, assigns drivers or vehicles, and monitors deliveries.',
    'Vehicle Inspector' => 'Examines vehicles for safety, compliance, and operational readiness.',
    'Truck Driver' => 'Transports goods over long distances, adheres to regulations, and ensures timely delivery.',
    'Shuttle Driver' => 'Operates shuttle services for employees, tourists, or passengers, maintaining schedules and safety.',
    'Transportation Officer' => 'Manages transport operations, monitors performance, and ensures regulatory compliance.',
    'Delivery Supervisor' => 'Oversees delivery teams, monitors performance, and ensures packages are delivered on time.',
    'Electrician' => 'Installs, repairs, and maintains electrical systems, wiring, and equipment in residential, commercial, or industrial settings.',
    'Welder' => 'Joins metal parts using welding techniques, interprets blueprints, and ensures strong, precise welds.',
    'Automotive Technician' => 'Inspects, repairs, and maintains vehicles, diagnosing mechanical and electrical problems.',
    'Carpenter' => 'Builds, repairs, and installs wooden structures, furniture, and fixtures.',
    'Plumber' => 'Installs and repairs pipes, fixtures, and plumbing systems for water supply and drainage.',
    'Mason' => 'Constructs and repairs structures using bricks, concrete, and stones, ensuring stability and design accuracy.',
    'HVAC Technician' => 'Installs, maintains, and repairs heating, ventilation, and air conditioning systems.',
    'CNC Operator' => 'Operates computer numerical control machines to produce precision parts according to specifications.',
    'Industrial Technician' => 'Performs technical tasks in industrial settings, including equipment maintenance, troubleshooting, and process support.',
    'Electronics Technician' => 'Installs, repairs, and maintains electronic devices and systems, including circuits and communication equipment.',
    'Refrigeration Technician' => 'Installs and maintains refrigeration systems, ensuring proper cooling and safety compliance.',
    'Machinist' => 'Operates machine tools to fabricate metal parts, following technical drawings and precision standards.',
    'Fabricator' => 'Assembles and constructs metal structures or components according to specifications.',
    'Pipefitter' => 'Installs and maintains piping systems for industrial, commercial, or residential use.',
    'Maintenance Technician' => 'Performs preventive maintenance, troubleshooting, and repairs on machinery and equipment.',
    'Tool and Die Maker' => 'Designs, builds, and maintains tools, dies, and molds for manufacturing processes.',
    'Hotel Manager' => 'Oversees hotel operations, manages staff, and ensures guest satisfaction.',
    'Chef' => 'Plans menus, prepares and cooks meals, manages kitchen staff, and ensures food quality and presentation.',
    'Sous Chef' => 'Assists the head chef in kitchen operations, supervises staff, and ensures smooth workflow in food preparation.',
    'Line Cook' => 'Prepares specific dishes or components on the kitchen line, maintaining speed and quality.',
    'Prep Cook' => 'Prepares ingredients, measures and organizes supplies, and assists in cooking under supervision.',
    'Grill Cook' => 'Prepares grilled dishes, monitors cooking temperatures, and ensures food quality and safety.',
    'Fry Cook' => 'Handles fried food preparation, ensures proper cooking techniques, and maintains cleanliness.',
    'Breakfast Cook' => 'Prepares breakfast menu items, including eggs, pancakes, and other morning specialties.',
    'Pastry / Dessert Cook' => 'Prepares baked goods, desserts, and pastries, ensuring taste and presentation standards.',
    'Baker' => 'Mixes and bakes bread, pastries, and other baked goods according to recipes and schedules.',
    'Barista' => 'Prepares coffee and specialty beverages, serves customers, and maintains coffee equipment.',
    'Crew Member' => 'Performs general tasks in restaurants or fast-food chains, including food preparation, serving, and cleaning.',
    'Restaurant Manager' => 'Oversees restaurant operations, manages staff, ensures customer satisfaction, and monitors financial performance.',
    'Kitchen Staff' => 'Supports kitchen operations, including food preparation, cleaning, and assisting chefs.',
    'Shift Supervisor' => 'Manages staff during a shift, ensures workflow efficiency, and resolves operational issues.',
    'Fast Food Crew' => 'Prepares food, serves customers, handles orders, and maintains cleanliness in fast-food establishments.',
    'Cashier' => 'Handles customer transactions, processes payments, and maintains accurate cash records.',
    'Host / Hostess' => 'Greets customers, manages reservations, and ensures smooth seating arrangements.',
    'Food Runner' => 'Delivers food orders from the kitchen to customers efficiently and accurately.',
    'Waiter / Waitress' => 'Takes orders, serves food and beverages, and ensures excellent customer service.',
    'Bartender' => 'Prepares and serves alcoholic and non-alcoholic drinks, manages bar inventory, and maintains cleanliness.',
    'Hotel Front Desk Officer' => 'Manages guest check-ins and check-outs, handles reservations, and provides information about hotel services.',
    'Concierge' => 'Assists guests with requests, provides local information, and ensures a pleasant stay experience.',
    'Tour Guide' => 'Leads and educates visitors on tours, providing information about historical, cultural, or natural sites.',
    'Event Coordinator' => 'Plans and manages events, ensuring smooth execution and client satisfaction.',
    'Catering Staff' => 'Prepares, serves, and manages food and beverages for events and gatherings.',
    'Store Manager' => 'Oversees store operations, manages staff, and drives sales performance.',
    'Sales Clerk' => 'Assists customers, handles transactions, and keeps the sales area organized.',
    'Merchandiser' => 'Arranges products, monitors stock levels, and ensures attractive displays.',
    'Promodiser' => 'Promotes products, assists customers, and supports sales activities.',
    'Inventory Staff' => 'Tracks inventory, handles stock receiving, and updates records.',
    'Construction Worker' => 'Performs construction tasks, follows safety procedures, and supports site operations.',
    'Sales Manager' => 'Leads the sales team, sets targets, and drives revenue growth.',
    'General Manager' => 'Oversees overall business operations, aligns departments, and drives company performance.',
    'COO' => 'Leads company operations, improves processes, and ensures efficient execution.',
    'CEO / President' => 'Sets strategic direction, drives company growth, and oversees executive decisions.',
    'Police Officer' => 'Enforces laws, maintains public order, prevents crime, and ensures community safety.',
    'Detective' => 'Investigates crimes, collects evidence, interviews witnesses, and solves criminal cases.',
    'Crime Scene Investigator' => 'Examines crime scenes, collects and analyzes evidence, and documents findings for investigations.',
    'Security Analyst' => 'Monitors security threats, analyzes data, and recommends strategies to prevent criminal activities.',
    'Forensic Specialist' => 'Conducts scientific analysis of evidence, including DNA, fingerprints, and other materials for investigations.',
    'Corrections Officer' => 'Supervises inmates in correctional facilities, maintains security, and enforces rules.',
    'Crime Analyst' => 'Studies crime patterns, compiles reports, and provides insights to law enforcement agencies.',
    'Intelligence Officer' => 'Gathers and analyzes information to support law enforcement and national security operations.',
    'Patrol Officer' => 'Monitors assigned areas, responds to incidents, and enforces laws to maintain public safety.',
    'Investigation Officer' => 'Conducts detailed investigations, gathers evidence, and prepares case reports.',
    'Police Chief' => 'Leads a police department, sets policies, oversees personnel, and ensures effective law enforcement.',
    'Detective Sergeant' => 'Supervises detectives, coordinates investigations, and ensures cases are efficiently solved.',
    'Crime Prevention Officer' => 'Develops and implements programs to reduce crime and educate the community on safety.',
    'Forensic Analyst' => 'Analyzes forensic evidence from crime scenes and provides expert reports for investigations.',
    'Security Guard' => 'Protects property and people, monitors premises, and prevents unauthorized access or theft.',
    'Security Supervisor' => 'Oversees security staff, manages schedules, and ensures adherence to security protocols.',
    'Loss Prevention Officer' => 'Monitors and investigates theft or loss incidents in retail or commercial settings.',
    'Bodyguard' => 'Provides personal protection to clients, assessing threats and ensuring safety.',
    'Security Coordinator' => 'Plans and coordinates security measures, manages personnel, and ensures operational readiness.',
    'Alarm Systems Officer' => 'Monitors alarm systems, responds to alerts, and ensures proper functioning of security equipment.',
    'CCTV Operator' => 'Monitors surveillance cameras, detects suspicious activities, and reports incidents.',
    'Security Consultant' => 'Advises organizations on security strategies, risk assessment, and policy development.',
    'Executive Protection Officer' => 'Provides close protection to high-profile individuals, including threat assessment and logistics planning.',
    'Event Security Officer' => 'Ensures safety and security at events, manages crowd control, and monitors access points.',
    'Security Officer' => 'Performs general security duties, patrols areas, and reports safety incidents.',
    'Security Manager' => 'Leads security operations, develops policies, and manages security personnel.',
    'Safety and Security Officer' => 'Ensures both workplace safety and security, conducts risk assessments, and implements preventive measures.',
    'Farm Manager' => 'Oversees daily farm operations, manages crops and livestock, plans budgets, and ensures productivity.',
    'Agronomist' => 'Studies soil, crops, and farming techniques to improve yield, quality, and sustainability.',
    'Fishery Technician' => 'Maintains aquaculture systems, monitors fish health, and supports breeding and production processes.',
    'Agricultural Laborer' => 'Performs manual tasks on farms such as planting, harvesting, and tending crops or livestock.',
    'Crop Specialist' => 'Provides expertise in crop management, pest control, and soil optimization to maximize production.',
    'Livestock Technician' => 'Cares for animals, monitors health, administers treatments, and supports breeding programs.',
    'Farm Equipment Operator' => 'Operates machinery such as tractors, harvesters, and irrigation systems for farming operations.',
    'Agriculture Extension Officer' => 'Educates farmers on modern techniques, best practices, and government programs to improve productivity.',
    'Horticulturist' => 'Cultivates plants, flowers, fruits, and vegetables, ensuring optimal growth and quality.',
    'Aquaculture Specialist' => 'Manages fish, shellfish, and aquatic plant production in controlled environments.',
    'Plantation Supervisor' => 'Oversees large-scale plantations, manages workers, and ensures crop production targets are met.',
    'Farm Inspector' => 'Inspects farms for compliance with regulations, quality standards, and safety protocols.',
    'Soil Scientist' => 'Studies soil composition, fertility, and health to recommend agricultural practices.',
    'Agriculture Technician' => 'Supports research, monitors crops and livestock, and assists in farm operations.',
    'Virtual Assistant' => 'Provides administrative support remotely, including scheduling, email management, and data entry.',
    'Freelance Writer' => 'Creates written content for websites, blogs, marketing materials, and other publications.',
    'Online Tutor' => 'Teaches students remotely, provides learning materials, and assists with academic progress.',
    'Graphic Designer' => 'Creates digital visuals, illustrations, and designs for clients or online projects.',
    'Content Creator' => 'Produces engaging digital content, including videos, blogs, social media posts, and other media.',
    'Social Media Manager' => 'Plans, creates, and manages social media content and campaigns to grow online presence.',
    'Web Developer' => 'Builds and maintains websites or web applications remotely, ensuring functionality and responsiveness.',
    'Data Entry Specialist' => 'Inputs, updates, and manages data accurately in remote systems or databases.',
    'Translator' => 'Converts written or spoken content from one language to another, maintaining accuracy and context.',
    'Remote Customer Support' => 'Provides customer service and technical support to clients via phone, chat, or email.',
    'Online Consultant' => 'Offers professional advice and guidance remotely in areas such as business, finance, or education.',
    'SEO Specialist' => 'Optimizes websites and content to improve search engine rankings and increase online visibility.',
    'Digital Marketing Freelancer' => 'Plans and executes online marketing campaigns, including social media, email, and PPC advertising.',
    'Video Editor – Remote' => 'Edits video content for clients or projects, adding effects, transitions, and finalizing outputs.',
    'Lawyer' => 'Provides legal advice, represents clients in court, drafts legal documents, and ensures compliance with laws.',
    'Paralegal' => 'Supports lawyers by conducting research, preparing legal documents, and managing case files.',
    'Government Officer' => 'Implements government programs, enforces policies, and serves the public in administrative roles.',
    'Legal Assistant' => 'Assists attorneys with case preparation, document management, and client communications.',
    'Policy Analyst' => 'Researches, evaluates, and develops policies for government agencies or organizations.',
    'Court Clerk' => 'Maintains court records, schedules hearings, and assists in the administrative functions of the court.',
    'Compliance Officer' => 'Ensures organizations comply with laws, regulations, and internal policies.',
    'Public Administrator' => 'Manages public sector programs, budgets, and services to meet community needs.',
    'Legal Researcher' => 'Conducts in-depth research on laws, precedents, and legal issues to support cases or policy-making.',
    'Legal Consultant' => 'Provides expert legal advice to organizations or individuals on regulatory compliance and legal matters.',
    'Judicial Clerk' => 'Assists judges with research, case summaries, and drafting opinions or rulings.',
    'Public Policy Officer' => 'Develops, monitors, and evaluates policies and programs to address social or economic issues.',
    'Court Officer' => 'Maintains security and order in the court, assists with legal proceedings, and supports court staff.',
    'Administrative Law Officer' => 'Reviews administrative processes, ensures compliance with regulations, and provides legal guidance.',
    'Ship Captain' => 'Commands a ship, ensures safe navigation, oversees crew operations, and maintains compliance with maritime regulations.',
    'Pilot' => 'Operates aircraft, ensures passenger and cargo safety, navigates flight routes, and follows aviation protocols.',
    'Flight Attendant' => 'Ensures passenger safety and comfort, provides in-flight services, and handles emergencies.',
    'Marine Engineer' => 'Maintains, repairs, and operates ship engines and onboard mechanical systems.',
    'Deck Officer' => 'Assists in navigation, ship handling, cargo operations, and safety management on board.',
    'Air Traffic Controller' => 'Monitors and directs aircraft movements, manages airspace, and ensures safe takeoffs and landings.',
    'Ship Engineer' => 'Operates and maintains all mechanical and electrical systems on a vessel, ensuring efficiency and safety.',
    'Cabin Crew' => 'Provides customer service, ensures passenger safety, and assists during in-flight emergencies.',
    'Marine Technician' => 'Maintains and repairs marine equipment, monitors systems, and supports ship operations.',
    'Aviation Safety Officer' => 'Develops and implements safety policies, monitors compliance, and investigates incidents in aviation operations.',
    'Port Officer' => 'Manages port operations, coordinates cargo handling, and ensures compliance with maritime regulations.',
    'Harbor Master' => 'Supervises harbor activities, controls vessel movements, and ensures safety and efficiency in port operations.',
    'Flight Dispatcher' => 'Plans flight paths, monitors weather and aircraft conditions, and communicates instructions to pilots.',
    'Research Scientist' => 'Conducts experiments, collects and analyzes data, and develops new scientific knowledge or products.',
    'Laboratory Technician' => 'Performs lab tests, maintains equipment, and assists scientists in experiments and research projects.',
    'Environmental Officer' => 'Monitors and implements environmental policies, ensures compliance, and promotes sustainability practices.',
    'Data Analyst' => 'Collects, processes, and analyzes scientific or environmental data to support research and decision-making.',
    'Biochemist' => 'Studies chemical processes in living organisms, conducts experiments, and develops applications in medicine or industry.',
    'Ecologist' => 'Studies ecosystems, biodiversity, and environmental impacts to support conservation and sustainable practices.',
    'Field Researcher' => 'Conducts on-site research, collects samples, and observes environmental or scientific phenomena.',
    'Microbiologist' => 'Studies microorganisms, conducts experiments, and applies findings in health, agriculture, or industry.',
    'Environmental Consultant' => 'Provides expert advice on environmental management, sustainability, and compliance with regulations.',
    'Lab Assistant' => 'Supports laboratory operations, prepares materials, and assists in experiments and data collection.',
    'Research Assistant' => 'Assists researchers in planning experiments, collecting data, and preparing reports.',
    'Marine Biologist' => 'Studies marine organisms, ecosystems, and environmental impacts in aquatic environments.',
    'Laboratory Analyst' => 'Conducts chemical or biological analyses in labs, interprets results, and ensures accuracy.',
    'Climate Scientist' => 'Studies climate patterns, predicts changes, and develops strategies to mitigate environmental impact.',
    'Actor' => 'Performs in theater, film, or television productions, portraying characters and conveying emotions.',
    'Musician' => 'Plays instruments, composes music, and performs live or recorded music for audiences.',
    'Dancer' => 'Performs choreographed routines, interprets music through movement, and participates in performances or shows.',
    'Cultural Program Coordinator' => 'Plans, organizes, and manages cultural events, festivals, or community programs.',
    'Singer' => 'Performs vocal music for live audiences, recordings, or media productions.',
    'Director' => 'Oversees creative vision in film, theater, or media projects, guiding performers and production teams.',
    'Photographer' => 'Captures images for artistic, commercial, or journalistic purposes, managing composition and lighting.',
    'Art Curator' => 'Manages art collections, plans exhibitions, and ensures preservation and documentation of artworks.',
    'Theater Performer' => 'Acts or performs on stage, participating in live productions and engaging audiences.',
    'Costume Designer' => 'Designs and creates costumes for performances, productions, or events, ensuring authenticity and creativity.',
    'Visual Artist' => 'Creates artwork using various mediums, such as painting, sculpture, or digital art, for display or sale.',
    'Film Editor' => 'Edits raw footage, assembles scenes, and produces finished films or videos.',
    'Choreographer' => 'Designs and directs dance routines, teaching performers and ensuring artistic expression.',
    'Stage Manager' => 'Organizes and coordinates all aspects of theater productions, ensuring smooth performances.',
    'Pastor' => 'Provides spiritual leadership, delivers sermons, offers counseling, and manages church activities.',
    'NGO Program Officer' => 'Plans, implements, and monitors NGO programs, ensuring they meet community needs and objectives.',
    'Social Worker' => 'Supports individuals and communities in need, providing assistance, counseling, and access to resources.',
    'Community Organizer' => 'Mobilizes communities, promotes awareness, and facilitates local development initiatives.',
    'Missionary' => 'Spreads religious teachings, provides community support, and engages in charitable activities.',
    'Development Officer' => 'Plans and executes development projects, secures funding, and monitors progress.',
    'Volunteer Coordinator' => 'Recruits, trains, and manages volunteers for community projects and events.',
    'Church Administrator' => 'Manages administrative operations of a church, including finances, records, and staff coordination.',
    'Program Manager' => 'Oversees NGO or development programs, manages staff, and ensures objectives are met.',
    'Cooperative Manager' => 'Manages cooperative operations, supervises members, and ensures sustainable business practices.',
    'Field Officer – NGO' => 'Implements projects in the field, monitors activities, and reports on community impact.',
    'Project Officer – NGO' => 'Coordinates and monitors specific projects, ensuring timely execution and compliance with guidelines.',
    'Community Development Officer' => 'Plans and implements initiatives to improve local community welfare and sustainability.',
    'Ethical Hacker' => 'Tests computer systems for vulnerabilities, identifies security weaknesses, and helps protect organizations from cyberattacks.',
    'Stunt Performer' => 'Performs dangerous or physically challenging actions in films, TV, or live shows safely under supervision.',
    'Ice Sculptor' => 'Creates artistic sculptures from ice for events, exhibitions, or competitions.',
    'Professional Gamer' => 'Competes in video game tournaments, streams gameplay, and may promote gaming brands.',
    'Escape Room Designer' => 'Designs puzzles, storylines, and challenges for escape room experiences.',
    'Drone Operator' => 'Operates drones for photography, surveying, inspections, or recreational purposes.',
    'Voice Actor' => 'Provides voiceovers for animation, advertisements, video games, or narration projects.',
    'Extreme Sports Instructor' => 'Teaches and supervises extreme sports activities such as skydiving, surfing, or rock climbing.',
    'Special Effects Artist' => 'Creates visual and practical effects for films, TV, or theater productions.',
    'Magician' => 'Performs magic tricks or illusions for entertainment purposes.',
    'Mystery Shopper' => 'Evaluates customer service, product quality, and store operations anonymously for feedback.',
    'Puppeteer' => 'Operates puppets for performances, educational programs, or entertainment shows.',
    'Forensic Artist' => 'Creates facial reconstructions, age progressions, or sketches for law enforcement investigations.',
    'Electrician' => 'Installs, maintains, and repairs electrical systems, ensuring safety and compliance with regulations.',
    'Water Plant Operator' => 'Operates and monitors water treatment systems, ensuring safe and clean water supply.',
    'Utility Technician' => 'Installs, maintains, and repairs utility systems, including electricity, gas, and water.',
    'Meter Reader' => 'Reads utility meters, records consumption data, and reports readings for billing purposes.',
    'Waste Management Officer' => 'Oversees waste collection, disposal, and recycling operations, ensuring environmental compliance.',
    'Line Worker' => 'Installs and maintains power lines or utility networks, ensuring uninterrupted services.',
    'Public Utility Engineer' => 'Designs, monitors, and maintains utility infrastructure for electricity, water, or gas.',
    'Maintenance Technician' => 'Conducts repairs and preventive maintenance of utility equipment and systems.',
    'Facility Officer' => 'Manages facilities and infrastructure, ensuring operational efficiency and safety.',
    'Energy Technician' => 'Monitors and maintains energy systems, ensuring optimal efficiency and sustainability.',
    'Water Treatment Technician' => 'Operates treatment plants, monitors water quality, and ensures compliance with health standards.',
    'Power Plant Operator' => 'Controls and monitors power generation equipment, ensuring reliable electricity supply.',
    'Telecommunications Technician' => 'Installs, maintains, and repairs telecom equipment and networks for voice, data, or internet services.',
    'Network Engineer' => 'Designs, implements, and maintains network infrastructure, ensuring connectivity and performance.',
    'Customer Support Specialist' => 'Provides technical assistance and troubleshooting for telecom products and services.',
    'Field Engineer' => 'Installs, tests, and maintains telecom systems on-site, ensuring proper functionality.',
    'Tower Technician' => 'Installs and maintains communication towers, antennas, and related equipment.',
    'Telecom Analyst' => 'Monitors network performance, analyzes issues, and provides solutions to optimize telecom services.',
    'Fiber Optic Technician' => 'Installs and maintains fiber optic cables and networks for high-speed data transmission.',
    'VoIP Specialist' => 'Configures and supports voice-over-IP systems, ensuring reliable telecommunication services.',
    'RF Engineer' => 'Designs, tests, and optimizes radio frequency networks for wireless communication.',
    'Service Coordinator' => 'Coordinates telecom service installation, maintenance, and troubleshooting tasks for clients.',
    'Telecom Sales Officer' => 'Promotes and sells telecom services or products to customers, meeting sales targets.',
    'Network Installation Technician' => 'Installs and configures network equipment and connectivity solutions for clients.',
    'Geologist' => 'Studies earth materials, conducts surveys, and provides analysis for resource exploration and environmental assessments.',
    'Mining Engineer' => 'Plans, designs, and oversees mining operations, ensuring safety and efficiency.',
    'Drill Operator' => 'Operates drilling equipment to extract minerals, core samples, or test sites for exploration.',
    'Safety Officer' => 'Monitors mining operations to ensure compliance with safety regulations and prevents accidents.',
    'Surveyor' => 'Measures and maps mining sites, providing data for planning and extraction activities.',
    'Mine Technician' => 'Supports mining operations with technical expertise, equipment maintenance, and site monitoring.',
    'Geotechnical Engineer' => 'Analyzes soil and rock properties to support safe mine design and construction.',
    'Mineral Analyst' => 'Examines mineral samples, evaluates composition, and provides reports for exploration or processing.',
    'Exploration Officer' => 'Conducts exploration activities, identifies potential mining sites, and assesses resource viability.',
    'Quarry Supervisor' => 'Manages quarry operations, oversees staff, and ensures productivity and safety compliance.',
    'Mine Surveyor' => 'Performs precise measurements and mapping of mining areas for operational planning and safety.',
    'Mining Safety Engineer' => 'Develops and implements safety protocols, conducts inspections, and trains staff to prevent accidents.',
    'Petroleum Engineer' => 'Designs methods to extract oil and gas efficiently, manages drilling operations, and optimizes production.',
    'Safety Officer' => 'Ensures compliance with safety regulations, conducts inspections, and implements risk prevention measures in oil and gas operations.',
    'Energy Analyst' => 'Analyzes energy markets, evaluates consumption patterns, and provides recommendations for efficiency and sustainability.',
    'Plant Operator' => 'Operates and monitors equipment in oil, gas, or energy plants, ensuring smooth production.',
    'Drilling Engineer' => 'Plans and supervises drilling operations, evaluates geological data, and optimizes well performance.',
    'Maintenance Technician' => 'Performs preventive maintenance, repairs, and troubleshooting of machinery and equipment in energy facilities.',
    'Field Operator' => 'Operates production equipment on-site, monitors processes, and reports anomalies.',
    'Pipeline Engineer' => 'Designs, monitors, and maintains pipelines for transporting oil, gas, or other fluids safely.',
    'Energy Consultant' => 'Provides advice on energy efficiency, alternative sources, and operational improvements for organizations.',
    'Refinery Technician' => 'Operates and maintains refining equipment, ensures product quality, and monitors safety compliance.',
    'Production Engineer – Oil & Gas' => 'Manages production processes, optimizes output, and solves technical issues in oil and gas facilities.',
    'Offshore Rig Technician' => 'Maintains and operates equipment on offshore drilling rigs, ensuring safety and productivity.',
    'Chemical Engineer' => 'Designs and manages chemical processes for manufacturing, ensuring efficiency, safety, and quality.',
    'Laboratory Technician' => 'Conducts chemical or industrial tests, maintains lab equipment, and records accurate results.',
    'Process Operator' => 'Operates industrial processes, monitors equipment, and ensures smooth production flow.',
    'Quality Analyst' => 'Tests and evaluates products, materials, or processes to ensure compliance with quality standards.',
    'Production Chemist' => 'Develops and monitors chemical production, ensuring proper formulation and quality control.',
    'Industrial Technician' => 'Supports manufacturing operations, maintains machinery, and assists in process optimization.',
    'Safety Officer' => 'Implements safety protocols, monitors industrial processes, and ensures compliance with regulations.',
    'Formulation Specialist' => 'Develops and tests chemical formulations for products in pharmaceuticals, cosmetics, or manufacturing.',
    'Research Chemist' => 'Conducts experiments and develops new chemical products or processes.',
    'Control Room Operator' => 'Monitors and controls industrial processes from centralized control systems, ensuring operational safety and efficiency.',
    'Plant Chemist' => 'Oversees chemical production processes, monitors reactions, and ensures product consistency.',
    'Industrial Safety Officer' => 'Ensures workplace safety in chemical or industrial plants, conducts inspections, and trains staff.',
    'Fitness Trainer' => 'Designs and leads exercise programs, monitors clients\' progress, and promotes healthy lifestyles.',
    'Coach' => 'Trains athletes or teams, develops strategies, and improves performance in specific sports.',
    'Sports Analyst' => 'Studies player performance, game statistics, and provides insights for teams or media.',
    'Recreation Coordinator' => 'Plans and organizes recreational activities and events for communities or organizations.',
    'Gym Instructor' => 'Guides clients in using gym equipment safely and effectively, providing training tips.',
    'Yoga Instructor' => 'Teaches yoga practices, promotes physical and mental wellness, and guides participants through routines.',
    'Athletic Trainer' => 'Assesses, prevents, and treats sports-related injuries, working closely with athletes.',
    'Sports Official' => 'Enforces rules, referees games, and ensures fair play during sporting events.',
    'Lifeguard' => 'Monitors swimming areas, ensures safety, and responds to emergencies in aquatic environments.',
    'Wellness Coach' => 'Provides guidance on fitness, nutrition, and lifestyle habits to improve overall well-being.',
    'Personal Trainer' => 'Offers individualized exercise programs, monitors client progress, and provides motivation.',
    'Sports Physiotherapist' => 'Provides rehabilitation, treatment, and preventive care for athletes\' injuries.',
    'Fashion Designer' => 'Creates clothing, accessories, or footwear designs, considering style, trends, and functionality.',
    'Stylist' => 'Selects clothing, accessories, and overall looks for clients, photoshoots, or events.',
    'Makeup Artist' => 'Applies makeup for clients, events, or media productions, enhancing appearance and style.',
    'Boutique Manager' => 'Oversees boutique operations, manages staff, inventory, and customer service.',
    'Hairdresser' => 'Cuts, styles, and treats hair, providing grooming and styling services for clients.',
    'Fashion Merchandiser' => 'Plans and promotes product lines, manages displays, and maximizes sales in fashion retail.',
    'Nail Technician' => 'Provides manicures, pedicures, and nail art services, maintaining hygiene and aesthetic standards.',
    'Costume Designer' => 'Designs costumes for theater, film, or events, ensuring authenticity and visual appeal.',
    'Wardrobe Consultant' => 'Advises clients on clothing choices, style, and personal branding.',
    'Beauty Therapist' => 'Provides skincare treatments, facials, and wellness services to clients.',
    'Fashion Illustrator' => 'Creates visual representations of clothing and accessories for design or marketing purposes.',
    'Image Consultant' => 'Guides clients on appearance, grooming, and presentation to enhance personal or professional image.',
    'Housekeeper' => 'Cleans, organizes, and maintains homes or facilities, ensuring hygiene and order.',
    'Nanny' => 'Cares for children, manages daily routines, and ensures their safety and well-being.',
    'Caregiver' => 'Provides assistance to elderly, sick, or disabled individuals with daily activities and personal care.',
    'Personal Trainer' => 'Develops exercise programs and provides guidance for clients\' fitness goals (can also work in-home).',
    'Driver' => 'Transports individuals or goods safely, maintains vehicle condition, and follows routes.',
    'Gardener' => 'Maintains gardens, plants, and outdoor spaces, ensuring aesthetics and plant health.',
    'Elderly Care Assistant' => 'Supports senior citizens with daily tasks, medication, and companionship.',
    'Pet Groomer' => 'Provides grooming services for pets, including bathing, trimming, and styling.',
    'Laundry Attendant' => 'Washes, irons, and maintains clothing and linens for households or establishments.',
    'Babysitter' => 'Supervises and cares for children for short-term periods, ensuring safety and engagement.',
    'Home Care Aide' => 'Provides personal care, housekeeping, and assistance to clients at home.',
    'Personal Assistant – Household' => 'Manages household tasks, schedules, and errands for families or individuals.',
    'Insurance Agent' => 'Sells insurance policies, advises clients on coverage options, and manages policy renewals.',
    'Risk Analyst' => 'Evaluates financial, operational, or market risks, and provides strategies to mitigate potential losses.',
    'Loan Officer' => 'Assesses loan applications, approves or recommends loans, and manages client accounts.',
    'Banking Teller' => 'Handles customer transactions, deposits, withdrawals, and provides basic banking services.',
    'Claims Adjuster' => 'Investigates insurance claims, evaluates damages, and determines settlements.',
    'Underwriter' => 'Analyzes risk factors and decides whether to approve insurance or loan applications.',
    'Financial Advisor' => 'Provides clients with guidance on investments, savings, and financial planning.',
    'Credit Analyst' => 'Assesses creditworthiness of individuals or organizations, analyzing financial statements and history.',
    'Investment Officer' => 'Manages investment portfolios, analyzes markets, and recommends investment strategies.',
    'Policy Consultant' => 'Advises clients or organizations on insurance policies, coverage, and risk management.',
    'Branch Banking Officer' => 'Oversees branch operations, manages staff, and ensures customer service and compliance.',
    'Insurance Underwriting Assistant' => 'Supports underwriters with data collection, risk assessment, and documentation.',
    'Delivery Rider' => 'Delivers packages, food, or goods to customers promptly and safely, often using motorcycles or bicycles.',
    'Vendor' => 'Sells products or services in public areas, markets, or events, often independently.',
    'Street Cleaner' => 'Maintains cleanliness in streets, public spaces, and sidewalks by sweeping, collecting trash, and disposing of waste.',
    'Construction Laborer' => 'Performs manual tasks on construction sites, assisting skilled workers and handling materials.',
    'Messenger' => 'Delivers documents, packages, or messages between locations efficiently.',
    'Market Seller' => 'Sells goods or produce in markets, manages inventory, and interacts with customers.',
    'Driver' => 'Transports people or goods for short-term or daily work, ensuring safety and punctuality.',
    'Helper' => 'Assists skilled workers in various tasks, including carrying materials, cleaning, or supporting operations.',
    'Day Laborer' => 'Performs temporary, unskilled labor for short periods, often paid daily.',
    'Errand Runner' => 'Completes various small tasks or errands for individuals or businesses.',
    'Food Cart Vendor' => 'Operates a mobile food cart, prepares food items, and serves customers on the street.',
    'Gig Worker' => 'Provides short-term services or tasks via digital platforms, including delivery, freelance work, or micro-jobs.',
    'Real Estate Agent' => 'Assists clients in buying, selling, or renting properties, providing market advice and facilitating transactions.',
    'Property Manager' => 'Manages properties, handles tenant relations, maintenance, and ensures profitability and compliance.',
    'Leasing Officer' => 'Oversees lease agreements, coordinates with tenants, and manages rental processes.',
    'Appraiser' => 'Evaluates properties to determine market value for sale, purchase, or financing purposes.',
    'Broker' => 'Acts as an intermediary between buyers and sellers, facilitating property transactions and negotiations.',
    'Real Estate Consultant' => 'Provides expert advice on property investments, market trends, and development opportunities.',
    'Valuation Officer' => 'Assesses property values for taxation, investment, or insurance purposes.',
    'Sales Executive' => 'Promotes and sells real estate properties, meeting sales targets and client needs.',
    'Development Manager' => 'Oversees property development projects, managing budgets, timelines, and contractors.',
    'Estate Manager' => 'Manages large estates or residential complexes, supervising staff, maintenance, and operations.',
    'Rental Officer' => 'Handles rental inquiries, manages contracts, and ensures tenants comply with lease agreements.',
    'Property Leasing Specialist' => 'Facilitates leasing processes, markets properties, and coordinates with clients and landlords.',
    'Chief Executive Officer' => 'Leads the organization, sets strategic direction, makes major corporate decisions, and oversees overall operations.',
    'Startup Founder' => 'Initiates and develops a new business venture, manages operations, funding, and growth strategies.',
    'Business Analyst' => 'Evaluates business processes, identifies opportunities for improvement, and recommends solutions.',
    'Operations Manager' => 'Oversees daily business operations, ensures efficiency, and manages resources and staff.',
    'Project Manager' => 'Plans, executes, and closes projects, managing timelines, budgets, and team performance.',
    'Management Consultant' => 'Advises organizations on strategy, operations, and problem-solving to improve performance.',
    'Entrepreneur' => 'Develops, launches, and manages businesses independently, assuming financial risk and responsibility.',
    'Strategic Planner' => 'Develops long-term business strategies, analyzes market trends, and supports organizational growth.',
    'Corporate Officer' => 'Executes corporate policies, manages departments, and supports executive leadership in business operations.',
    'Business Development Manager' => 'Identifies business opportunities, builds relationships, and drives revenue growth.',
    'Operations Analyst' => 'Analyzes operational data, identifies inefficiencies, and provides recommendations for improvement.',
    'Executive Director' => 'Leads nonprofit or corporate organizations, manages programs, staff, and ensures achievement of organizational goals.',
];
$jobTitleDescriptions = [];
foreach ($jobTitleGroups as $groupLabel => $titles) {
    $categoryKey = $jobTitleGroupCategoryMap[$groupLabel] ?? '';
    $defaultDescription = $categoryDefaultDescriptions[$categoryKey] ?? '';
    foreach ($titles as $title) {
        if (isset($jobTitleDescriptionOverrides[$title])) {
            $jobTitleDescriptions[$title] = $jobTitleDescriptionOverrides[$title];
        } elseif (!empty($defaultDescription)) {
            $jobTitleDescriptions[$title] = $defaultDescription;
        }
    }
}
$selectedCategoryKey = strtolower(trim($selectedCategoryName));
$requirementsOptions = $categorySkillOptions[$selectedCategoryKey] ?? [];

// Job title to specific required skills mapping
$jobTitleToSkills = [
    // Administrative / Office
    'Office Administrator' => [
        'Office management & coordination',
        'Scheduling & calendar management',
        'Record keeping & filing systems',
        'Communication (verbal & written)',
        'MS Office / Google Workspace proficiency',
        'Problem-solving & decision-making',
        'Basic HR/admin processes'
    ],
    'Executive Assistant' => [
        'Supporting senior executives',
        'Calendar & travel management',
        'Confidentiality & discretion',
        'Meeting & event coordination',
        'Strong written & verbal communication',
        'Document preparation (reports, presentations)',
        'Multitasking & prioritization'
    ],
    'Administrative Coordinator' => [
        'Office workflow coordination',
        'Task & project tracking',
        'Scheduling & resource allocation',
        'Communication & liaison skills',
        'Record keeping & reporting',
        'Problem-solving & process improvement'
    ],
    'Data Entry Clerk' => [
        'Fast & accurate typing',
        'Attention to detail',
        'Basic computer skills (Excel, databases)',
        'Data verification & quality control',
        'Time management'
    ],
    'Office Manager' => [
        'Team management & leadership',
        'Office operations & logistics',
        'Budgeting & procurement',
        'Scheduling & event coordination',
        'Policy & procedure implementation',
        'Communication & problem-solving'
    ],
    'Receptionist' => [
        'Customer service & friendliness',
        'Phone etiquette & call handling',
        'Visitor management',
        'Basic administrative support',
        'Scheduling appointments',
        'Multitasking'
    ],
    'Personal Assistant' => [
        'Executive support',
        'Scheduling & calendar management',
        'Travel & meeting coordination',
        'Confidentiality & discretion',
        'Task prioritization',
        'Communication & interpersonal skills'
    ],
    'Administrative Officer' => [
        'Office administration & operations',
        'Record keeping & document management',
        'Reporting & correspondence',
        'Policy & procedure adherence',
        'Coordination with staff & departments'
    ],
    'Records Clerk' => [
        'File management & archiving',
        'Data accuracy & indexing',
        'Retrieval of documents',
        'Attention to detail',
        'Basic computer skills'
    ],
    'Operations Assistant' => [
        'Workflow & process support',
        'Scheduling & coordination',
        'Reporting & documentation',
        'Problem-solving & organization',
        'Communication skills'
    ],
    'Secretary' => [
        'Typing & document preparation',
        'Scheduling & correspondence',
        'Filing & record keeping',
        'Communication & coordination',
        'Reception & phone handling'
    ],
    'Front Desk Officer' => [
        'Customer service & reception',
        'Phone & email handling',
        'Visitor & appointment management',
        'Basic administrative tasks',
        'Organization & multitasking'
    ],
    'Executive Secretary' => [
        'Executive support & correspondence',
        'Scheduling & meeting coordination',
        'Document drafting & report preparation',
        'Confidentiality & discretion',
        'Event planning & coordination'
    ],
    'Office Clerk' => [
        'Filing & record keeping',
        'Data entry & document handling',
        'Mail & correspondence management',
        'Basic computer skills',
        'Organizational skills'
    ],
    'Filing Clerk' => [
        'Document sorting & filing',
        'Indexing & retrieval',
        'Accuracy & attention to detail',
        'Basic administrative tasks',
        'Data entry'
    ],
    'Scheduling Coordinator' => [
        'Calendar & appointment management',
        'Meeting & event coordination',
        'Communication & liaison',
        'Time management & multitasking',
        'Organizational skills'
    ],
    'Office Services Manager' => [
        'Office operations & facilities management',
        'Vendor & supplier coordination',
        'Team supervision & support',
        'Budgeting & procurement',
        'Problem-solving & planning'
    ],
    'Documentation Specialist' => [
        'Document creation & management',
        'Filing & archiving',
        'Attention to detail',
        'Compliance with standards',
        'Record accuracy & verification'
    ],
    'Office Support Specialist' => [
        'Administrative support & coordination',
        'Document handling & filing',
        'Communication & customer service',
        'Scheduling & office logistics',
        'Problem-solving'
    ],
    'Office Supervisor' => [
        'Team leadership & supervision',
        'Office workflow management',
        'Task delegation & monitoring',
        'Reporting & documentation',
        'Communication & conflict resolution'
    ],
    
    // Customer Service / BPO
    'Customer Service Representative' => [
        'Verbal & written communication',
        'Problem-solving & conflict resolution',
        'Active listening & empathy',
        'Basic computer literacy',
        'Multitasking & time management',
        'CRM software knowledge (optional)'
    ],
    'Call Center Agent' => [
        'Phone etiquette & clear communication',
        'Script adherence & call handling',
        'Customer support & problem resolution',
        'Typing & data entry during calls',
        'Stress management'
    ],
    'Client Support Specialist' => [
        'Customer relationship management',
        'Technical troubleshooting (if product-based)',
        'Communication & interpersonal skills',
        'Product knowledge',
        'CRM software proficiency'
    ],
    'Help Desk Associate' => [
        'Technical troubleshooting (hardware/software)',
        'Ticketing system management',
        'Communication & guidance',
        'Problem-solving & escalation handling',
        'Documentation & reporting'
    ],
    'Customer Care Coordinator' => [
        'Coordinating customer service tasks',
        'Communication & follow-ups',
        'Scheduling & task management',
        'Reporting & data tracking',
        'Problem-solving & escalation management'
    ],
    'Technical Support Representative' => [
        'Technical troubleshooting & diagnostics',
        'Product/service expertise',
        'Communication & active listening',
        'Patience & empathy',
        'Knowledge of support tools & ticketing systems'
    ],
    'Service Desk Analyst' => [
        'IT support & troubleshooting',
        'Ticketing & incident management',
        'Communication & problem documentation',
        'Prioritization & multitasking',
        'Knowledge of systems & networks'
    ],
    'Account Support Specialist' => [
        'Account management & client coordination',
        'Problem-solving & resolution',
        'Communication & relationship-building',
        'Reporting & documentation',
        'CRM & database proficiency'
    ],
    'Call Center Supervisor' => [
        'Team leadership & coaching',
        'Performance monitoring & reporting',
        'Conflict resolution & motivation',
        'Scheduling & resource management',
        'Communication & escalation handling'
    ],
    'Customer Experience Associate' => [
        'Customer engagement & support',
        'Feedback collection & analysis',
        'Communication & empathy',
        'Problem-solving & follow-ups',
        'Product/service knowledge'
    ],
    'Contact Center Trainer' => [
        'Training & onboarding new hires',
        'Presentation & communication skills',
        'Knowledge of policies & procedures',
        'Coaching & mentoring',
        'Feedback & evaluation skills'
    ],
    'Chat Support Agent' => [
        'Written communication & typing speed',
        'Multitasking & handling multiple chats',
        'Customer problem-solving',
        'Knowledge of product/service',
        'CRM/chat software proficiency'
    ],
    'Email Support Specialist' => [
        'Professional written communication',
        'Typing & grammar accuracy',
        'Email ticket management',
        'Problem-solving & follow-up',
        'CRM/email support software knowledge'
    ],
    'Escalation Officer' => [
        'Handling complex customer issues',
        'Problem-solving & critical thinking',
        'Communication & negotiation',
        'Decision-making & discretion',
        'Knowledge of policies & procedures'
    ],
    'QA Analyst (Customer Service)' => [
        'Monitoring calls/chats for quality',
        'Analytical & observation skills',
        'Communication & reporting',
        'Feedback & coaching',
        'Process improvement'
    ],
    'Customer Retention Specialist' => [
        'Persuasion & negotiation',
        'Relationship management',
        'Problem-solving & empathy',
        'Product knowledge',
        'Communication & follow-up'
    ],
    'Virtual Customer Service Associate' => [
        'Remote communication & tech skills',
        'Problem-solving & multitasking',
        'CRM & virtual collaboration tools',
        'Time management & self-discipline',
        'Customer support & empathy'
    ],
    'Inside Sales / Customer Support' => [
        'Sales & upselling techniques',
        'Communication & negotiation',
        'Customer service & problem-solving',
        'CRM & lead tracking',
        'Product knowledge'
    ],
    'Team Lead – Customer Support' => [
        'Team supervision & leadership',
        'Performance monitoring & coaching',
        'Escalation management',
        'Scheduling & workload allocation',
        'Communication & conflict resolution'
    ],
    
    // Education
    'Teacher' => [
        'Lesson planning & curriculum delivery',
        'Classroom management',
        'Communication & presentation',
        'Assessment & evaluation',
        'Subject matter expertise',
        'Adaptability & creativity'
    ],
    'School Counselor' => [
        'Student guidance & support',
        'Active listening & empathy',
        'Career & academic advising',
        'Conflict resolution & problem-solving',
        'Confidentiality & ethics',
        'Record keeping & reporting'
    ],
    'Academic Coordinator' => [
        'Curriculum planning & coordination',
        'Teacher support & training',
        'Scheduling & resource allocation',
        'Data tracking & reporting',
        'Communication & leadership'
    ],
    'Tutor' => [
        'Subject matter expertise',
        'One-on-one instruction & mentoring',
        'Patience & adaptability',
        'Communication & explanation skills',
        'Progress tracking & assessment'
    ],
    'Principal' => [
        'School leadership & administration',
        'Staff supervision & development',
        'Policy implementation',
        'Communication & conflict resolution',
        'Strategic planning & decision-making',
        'Budget & resource management'
    ],
    'Librarian' => [
        'Cataloging & information management',
        'Research assistance & literacy promotion',
        'Library software & database knowledge',
        'Organizational & administrative skills',
        'Customer service (students & staff)'
    ],
    'Special Education Teacher' => [
        'Individualized Education Program (IEP) design & implementation',
        'Inclusive teaching strategies',
        'Patience & empathy',
        'Behavior management',
        'Communication with parents & staff'
    ],
    'Curriculum Developer' => [
        'Curriculum design & lesson planning',
        'Educational standards knowledge',
        'Content creation & assessment design',
        'Analytical & research skills',
        'Collaboration with educators'
    ],
    'Education Program Manager' => [
        'Program planning & execution',
        'Team management & coordination',
        'Budgeting & resource management',
        'Reporting & evaluation',
        'Communication & stakeholder engagement'
    ],
    'Lecturer' => [
        'Subject matter expertise',
        'Lesson preparation & delivery',
        'Academic research & publication',
        'Assessment & grading',
        'Public speaking & presentation skills'
    ],
    'College Instructor' => [
        'Advanced subject knowledge',
        'Lesson planning & teaching',
        'Student assessment & mentoring',
        'Research & academic writing',
        'Communication & critical thinking'
    ],
    'Preschool Teacher' => [
        'Early childhood education principles',
        'Classroom & behavior management',
        'Creativity & play-based learning',
        'Communication with parents',
        'Patience & nurturing'
    ],
    'Teaching Assistant' => [
        'Classroom support',
        'Student supervision',
        'Lesson preparation & material support',
        'Communication & collaboration with teachers',
        'Patience & adaptability'
    ],
    'Instructional Designer' => [
        'Curriculum & e-learning design',
        'Instructional technology proficiency',
        'Content development & assessment creation',
        'Analytical & research skills',
        'Project management'
    ],
    'Learning Facilitator' => [
        'Workshop & session facilitation',
        'Communication & engagement skills',
        'Lesson delivery & activity planning',
        'Assessment & feedback',
        'Adaptability & problem-solving'
    ],
    'Education Consultant' => [
        'Educational assessment & advising',
        'Program evaluation & improvement',
        'Research & analytical skills',
        'Communication & presentation',
        'Stakeholder management'
    ],
    'Homeroom Teacher' => [
        'Classroom management',
        'Lesson planning & delivery',
        'Student guidance & support',
        'Communication with parents & staff',
        'Monitoring student progress'
    ],
    'School Administrator' => [
        'School operations & management',
        'Staff supervision & coordination',
        'Policy implementation',
        'Budgeting & scheduling',
        'Communication & leadership'
    ],
    'Guidance Counselor' => [
        'Academic & career guidance',
        'Emotional support & counseling',
        'Active listening & problem-solving',
        'Confidentiality & ethics',
        'Student record keeping'
    ],
    'Academic Adviser' => [
        'Academic planning & course selection guidance',
        'Student mentorship & support',
        'Communication & problem-solving',
        'Record keeping & reporting',
        'Knowledge of academic policies'
    ],
    
    // Engineering
    'Civil Engineer' => [
        'Structural analysis & design',
        'Project planning & management',
        'Surveying & site inspection',
        'Knowledge of building codes & standards',
        'AutoCAD / Civil 3D proficiency',
        'Problem-solving & analytical thinking'
    ],
    'Mechanical Engineer' => [
        'Mechanical design & analysis',
        'CAD software (SolidWorks, AutoCAD, CATIA)',
        'Thermodynamics & fluid mechanics',
        'Manufacturing processes',
        'Troubleshooting & problem-solving',
        'Project management'
    ],
    'Electrical Engineer' => [
        'Circuit design & analysis',
        'Power systems & electronics',
        'PLC & control systems',
        'Troubleshooting electrical systems',
        'Technical documentation & compliance',
        'Analytical & problem-solving skills'
    ],
    'Project Engineer' => [
        'Project planning & scheduling',
        'Budget & resource management',
        'Coordination with teams & stakeholders',
        'Risk management & problem-solving',
        'Technical understanding of project field'
    ],
    'Structural Engineer' => [
        'Structural analysis & design (buildings, bridges)',
        'Knowledge of codes & safety standards',
        'CAD / structural design software',
        'Material science & stress analysis',
        'Problem-solving & critical thinking'
    ],
    'Chemical Engineer' => [
        'Process design & optimization',
        'Chemical reaction analysis & safety',
        'Laboratory & experimental skills',
        'Knowledge of chemical regulations & compliance',
        'Problem-solving & analytical thinking'
    ],
    'Industrial Engineer' => [
        'Process optimization & workflow analysis',
        'Lean manufacturing & Six Sigma',
        'Productivity improvement & efficiency',
        'Data analysis & reporting',
        'Project management & teamwork'
    ],
    'Process Engineer' => [
        'Process design & optimization',
        'Production & manufacturing knowledge',
        'Safety & compliance regulations',
        'Problem-solving & troubleshooting',
        'Data analysis & process monitoring'
    ],
    'Quality Engineer' => [
        'Quality assurance & control',
        'ISO standards & regulatory compliance',
        'Testing & inspection',
        'Problem-solving & root cause analysis',
        'Documentation & reporting'
    ],
    'Design Engineer' => [
        'Product / mechanical / electrical design',
        'CAD / CAM / SolidWorks / AutoCAD',
        'Prototyping & modeling',
        'Material selection & analysis',
        'Problem-solving & creativity'
    ],
    'Maintenance Engineer' => [
        'Equipment maintenance & troubleshooting',
        'Preventive & corrective maintenance planning',
        'Mechanical / electrical skills depending on field',
        'Technical documentation',
        'Problem-solving & safety compliance'
    ],
    'Field Engineer' => [
        'On-site engineering & inspection',
        'Project coordination & reporting',
        'Problem-solving & troubleshooting',
        'Technical knowledge of field (civil, mechanical, electrical)',
        'Communication & adaptability'
    ],
    'Systems Engineer' => [
        'Systems design & integration',
        'Requirement analysis & documentation',
        'Problem-solving & analytical thinking',
        'Knowledge of IT / control systems',
        'Testing & validation'
    ],
    'Engineering Technician' => [
        'Technical drawing & documentation',
        'Equipment maintenance & testing',
        'Data collection & reporting',
        'Hands-on problem-solving',
        'Technical software proficiency'
    ],
    'Automation Engineer' => [
        'PLC programming & control systems',
        'Robotics & automation design',
        'Process optimization',
        'Troubleshooting & problem-solving',
        'Technical documentation & compliance'
    ],
    'Product Design Engineer' => [
        'Product concept development & prototyping',
        'CAD / 3D modeling & simulation',
        'Material selection & manufacturing knowledge',
        'Problem-solving & creativity',
        'Project coordination'
    ],
    'Control Systems Engineer' => [
        'PLC / SCADA / DCS systems',
        'Automation & process control',
        'Troubleshooting & problem-solving',
        'Systems integration',
        'Technical documentation & compliance'
    ],
    'Environmental Engineer' => [
        'Environmental assessment & compliance',
        'Pollution control & sustainability solutions',
        'Waste management & remediation',
        'Regulatory knowledge & reporting',
        'Problem-solving & analytical skills'
    ],
    'Safety Engineer' => [
        'Occupational safety & hazard assessment',
        'Risk management & compliance',
        'Safety audits & inspections',
        'Emergency response planning',
        'Communication & training'
    ],
    'Reliability Engineer' => [
        'Equipment reliability & failure analysis',
        'Predictive & preventive maintenance',
        'Data analysis & performance metrics',
        'Root cause analysis & problem-solving',
        'Technical documentation & reporting'
    ],
    
    // Information Technology (IT)
    'Software Developer' => ['Programming (Java, Python, C#)', 'Problem-Solving', 'Debugging', 'Git', 'Communication'],
    'Network Administrator' => ['Networking', 'Troubleshooting', 'Security', 'Configuration', 'Communication'],
    'IT Support Specialist' => ['Troubleshooting', 'Customer Service', 'Communication', 'Hardware/Software Knowledge', 'Problem-Solving'],
    'Web Developer' => ['HTML/CSS/JS', 'Front-End/Back-End', 'Responsive Design', 'Problem-Solving', 'Git'],
    'Systems Analyst' => ['Analytical Thinking', 'Requirements Gathering', 'Documentation', 'Communication', 'Problem-Solving'],
    'Database Administrator' => ['SQL', 'Database Design', 'Backup & Recovery', 'Security', 'Troubleshooting'],
    'Cybersecurity Analyst' => ['Network Security', 'Risk Assessment', 'Ethical Hacking', 'Analytical Skills', 'Incident Response'],
    'Cloud Engineer' => ['Cloud Platforms (AWS/Azure)', 'Deployment', 'Scripting', 'Security', 'Troubleshooting'],
    'IT Manager' => ['Leadership', 'Project Management', 'IT Strategy', 'Communication', 'Problem-Solving'],
    'Technical Lead' => ['Leadership', 'Programming', 'Code Review', 'Problem-Solving', 'Communication'],
    'Application Developer' => ['Programming', 'Problem-Solving', 'Testing', 'Version Control', 'Communication'],
    'DevOps Engineer' => ['CI/CD', 'Scripting', 'Cloud Platforms', 'Monitoring', 'Troubleshooting'],
    'Mobile App Developer' => ['Mobile Platforms (iOS/Android)', 'Programming', 'UI/UX', 'Testing', 'Problem-Solving'],
    'Data Engineer' => ['SQL', 'ETL', 'Data Modeling', 'Python/Scala', 'Big Data Tools'],
    'Network Security Engineer' => ['Firewalls', 'Intrusion Detection', 'VPN', 'Security Policies', 'Troubleshooting'],
    'IT Project Manager' => ['Project Planning', 'Communication', 'Risk Management', 'Leadership', 'Problem-Solving'],
    'UX/UI Developer' => ['Design Principles', 'Prototyping', 'HTML/CSS', 'User Research', 'Communication'],
    'Front-End Developer' => ['HTML/CSS/JS', 'Responsive Design', 'Frameworks', 'Debugging', 'Problem-Solving'],
    'Back-End Developer' => ['Server-Side Programming', 'Databases', 'API Development', 'Debugging', 'Security'],
    'IT Infrastructure Engineer' => ['Networking', 'Servers', 'Virtualization', 'Troubleshooting', 'Documentation'],
    'IT Consultant' => ['Technical Expertise', 'Communication', 'Problem-Solving', 'Analytical Skills', 'Client Management'],
    'IT Auditor' => ['Risk Assessment', 'Compliance', 'Analytical Thinking', 'Reporting', 'IT Knowledge'],
    
    // Finance / Accounting
    'Accountant' => [
        'Financial reporting & bookkeeping',
        'General ledger management',
        'Budgeting & forecasting',
        'Accounting software (QuickBooks, SAP, Xero)',
        'Attention to detail & accuracy',
        'Regulatory compliance'
    ],
    'Financial Analyst' => [
        'Financial modeling & analysis',
        'Budgeting & forecasting',
        'Data interpretation & reporting',
        'Excel / spreadsheets & analytics tools',
        'Business acumen & problem-solving'
    ],
    'Bookkeeper' => [
        'Recording financial transactions',
        'Accounts reconciliation',
        'Payroll processing',
        'Accounting software (QuickBooks, MYOB)',
        'Attention to detail & organization'
    ],
    'Payroll Officer' => [
        'Payroll processing & management',
        'Tax calculations & deductions',
        'Compliance with labor laws',
        'Accounting software proficiency',
        'Confidentiality & accuracy'
    ],
    'Tax Specialist' => [
        'Tax preparation & filing',
        'Knowledge of local & international tax laws',
        'Tax planning & advisory',
        'Accounting & ERP software',
        'Analytical & compliance skills'
    ],
    'Budget Analyst' => [
        'Budget planning & monitoring',
        'Forecasting & variance analysis',
        'Financial reporting & presentations',
        'Excel / data analysis',
        'Communication & decision-making'
    ],
    'Auditor' => [
        'Financial audit & control evaluation',
        'Risk assessment & compliance',
        'Accounting standards & regulations',
        'Report preparation & documentation',
        'Analytical & investigative skills'
    ],
    'Finance Manager' => [
        'Financial planning & strategy',
        'Budgeting & forecasting',
        'Team management & leadership',
        'Financial reporting & analysis',
        'Regulatory compliance & risk management'
    ],
    'Credit Analyst' => [
        'Credit risk assessment & evaluation',
        'Financial statement analysis',
        'Loan underwriting & recommendations',
        'Decision-making & problem-solving',
        'Communication with stakeholders'
    ],
    'Controller' => [
        'Oversight of accounting operations',
        'Financial reporting & consolidation',
        'Budgeting & cash flow management',
        'Compliance & internal controls',
        'Leadership & team management'
    ],
    'Cost Accountant' => [
        'Cost analysis & allocation',
        'Budgeting & inventory costing',
        'Financial reporting & variance analysis',
        'Accounting software & ERP systems',
        'Analytical & problem-solving skills'
    ],
    'Treasury Analyst' => [
        'Cash flow management',
        'Liquidity & investment tracking',
        'Risk management & compliance',
        'Financial modeling & reporting',
        'Analytical & decision-making skills'
    ],
    'Accounts Payable Clerk' => [
        'Invoice processing & payment tracking',
        'Vendor management',
        'Reconciliation & record keeping',
        'Accounting software proficiency',
        'Attention to detail & accuracy'
    ],
    'Accounts Receivable Clerk' => [
        'Billing & collections management',
        'Customer account monitoring',
        'Reconciliation & reporting',
        'Accounting software proficiency',
        'Communication & organization'
    ],
    'Finance Officer' => [
        'Financial reporting & budgeting',
        'Accounting & bookkeeping',
        'Compliance & internal controls',
        'Analytical & problem-solving skills',
        'Software proficiency (Excel, ERP)'
    ],
    'Investment Analyst' => [
        'Investment research & analysis',
        'Portfolio management & risk assessment',
        'Financial modeling & forecasting',
        'Market & industry knowledge',
        'Analytical & decision-making skills'
    ],
    'Risk Officer' => [
        'Risk assessment & mitigation',
        'Financial & operational risk analysis',
        'Compliance & regulatory knowledge',
        'Reporting & documentation',
        'Analytical & problem-solving skills'
    ],
    'Compliance Officer – Finance' => [
        'Regulatory compliance monitoring',
        'Policy & procedure implementation',
        'Audit & reporting',
        'Risk assessment & management',
        'Attention to detail & ethics'
    ],
    'Loan Officer' => [
        'Loan processing & evaluation',
        'Credit analysis & risk assessment',
        'Customer communication & advisory',
        'Compliance with lending regulations',
        'Attention to detail & decision-making'
    ],
    'Fund Accountant' => [
        'Fund accounting & reconciliation',
        'Financial reporting for funds',
        'NAV calculation & compliance',
        'Accounting software proficiency',
        'Analytical & detail-oriented'
    ],
    'Billing Officer' => [
        'Invoice preparation & billing management',
        'Customer account monitoring',
        'Reconciliation & reporting',
        'Accounting software proficiency',
        'Accuracy & attention to detail'
    ],
    'Treasury Officer' => [
        'Cash flow & liquidity management',
        'Investment tracking & reporting',
        'Risk assessment & compliance',
        'Financial analysis & forecasting',
        'Attention to detail & accuracy'
    ],
    
    // Healthcare / Medical
    'Doctor' => [
        'Diagnosis & treatment planning',
        'Medical knowledge & clinical skills',
        'Patient examination & monitoring',
        'Communication & empathy',
        'Record keeping & documentation',
        'Critical thinking & decision-making'
    ],
    'Physician' => [
        'Diagnosis & treatment planning',
        'Medical knowledge & clinical skills',
        'Patient examination & monitoring',
        'Communication & empathy',
        'Record keeping & documentation',
        'Critical thinking & decision-making'
    ],
    'Nurse' => [
        'Patient care & monitoring',
        'Medication administration & documentation',
        'Vital signs assessment',
        'Patient education & communication',
        'Clinical procedures & safety protocols',
        'Compassion & teamwork'
    ],
    'Medical Technologist' => [
        'Laboratory testing & analysis',
        'Sample collection & processing',
        'Equipment operation & calibration',
        'Quality control & compliance',
        'Data interpretation & reporting'
    ],
    'Pharmacist' => [
        'Medication dispensing & verification',
        'Drug interactions & counseling',
        'Prescription review & compliance',
        'Inventory management',
        'Communication & patient education'
    ],
    'Dentist' => [
        'Oral examination & diagnosis',
        'Dental procedures & surgery',
        'Patient education & preventive care',
        'Sterilization & safety protocols',
        'Record keeping & documentation'
    ],
    'Radiologic Technologist' => [
        'Medical imaging & radiography',
        'Equipment operation (X-ray, CT, MRI)',
        'Patient positioning & safety',
        'Image analysis & reporting',
        'Compliance with radiation safety'
    ],
    'Physical Therapist' => [
        'Rehabilitation & therapy planning',
        'Patient assessment & exercise instruction',
        'Manual therapy & mobility improvement',
        'Communication & motivation',
        'Record keeping & progress tracking'
    ],
    'Occupational Therapist' => [
        'Rehabilitation & daily living support',
        'Patient assessment & intervention planning',
        'Adaptive equipment training',
        'Communication & patient education',
        'Progress documentation'
    ],
    'Laboratory Technician' => [
        'Sample collection & preparation',
        'Lab testing & analysis',
        'Equipment operation & maintenance',
        'Data recording & reporting',
        'Compliance with safety protocols'
    ],
    'Midwife' => [
        'Prenatal & postnatal care',
        'Labor & delivery support',
        'Patient education & counseling',
        'Emergency response & clinical skills',
        'Record keeping & documentation'
    ],
    'Paramedic' => [
        'Emergency response & patient assessment',
        'Life support & first aid',
        'Rapid decision-making & triage',
        'Communication & teamwork',
        'Documentation & reporting'
    ],
    'Emergency Medical Technician' => [
        'Emergency response & patient assessment',
        'Life support & first aid',
        'Rapid decision-making & triage',
        'Communication & teamwork',
        'Documentation & reporting'
    ],
    'Dietitian' => [
        'Nutrition assessment & meal planning',
        'Dietary counseling & education',
        'Food safety & regulatory compliance',
        'Analytical & problem-solving skills',
        'Communication & empathy'
    ],
    'Nurse Practitioner' => [
        'Advanced patient assessment & diagnosis',
        'Medication prescribing & management',
        'Clinical decision-making',
        'Patient education & counseling',
        'Collaboration with healthcare teams'
    ],
    'Anesthesiologist' => [
        'Anesthesia administration & monitoring',
        'Patient evaluation & risk assessment',
        'Clinical decision-making in surgery',
        'Crisis management & problem-solving',
        'Communication with surgical team'
    ],
    'Surgeon' => [
        'Surgical procedures & techniques',
        'Patient assessment & pre/post-op care',
        'Sterilization & safety protocols',
        'Teamwork & communication',
        'Critical thinking & decision-making'
    ],
    'Medical Assistant' => [
        'Clinical support & patient care',
        'Vital signs & basic procedures',
        'Administrative tasks (scheduling, records)',
        'Communication & patient interaction',
        'Compliance & safety'
    ],
    'Health Information Technician' => [
        'Medical records management & coding',
        'HIPAA / patient privacy compliance',
        'Data entry & database management',
        'Attention to detail & accuracy',
        'Reporting & documentation'
    ],
    'Speech Therapist' => [
        'Speech & language assessment',
        'Therapy planning & intervention',
        'Patient instruction & progress tracking',
        'Communication & empathy',
        'Documentation & reporting'
    ],
    'Psychologist' => [
        'Patient assessment & diagnosis',
        'Counseling & therapy techniques',
        'Research & data analysis',
        'Communication & empathy',
        'Ethical & confidential practice'
    ],
    'Care Coordinator' => [
        'Patient care planning & coordination',
        'Communication with healthcare teams',
        'Scheduling & administrative support',
        'Documentation & compliance',
        'Problem-solving & case management'
    ],
    'Clinical Coordinator' => [
        'Patient care planning & coordination',
        'Communication with healthcare teams',
        'Scheduling & administrative support',
        'Documentation & compliance',
        'Problem-solving & case management'
    ],
    
    // Human Resources (HR)
    'HR Manager' => [
        'Strategic HR planning & leadership',
        'Employee relations & conflict resolution',
        'Recruitment & retention strategies',
        'Performance management & appraisals',
        'Policy development & compliance',
        'Communication & decision-making'
    ],
    'Recruitment Specialist' => [
        'Talent sourcing & acquisition',
        'Interviewing & candidate evaluation',
        'ATS (Applicant Tracking System) proficiency',
        'Employer branding & networking',
        'Communication & negotiation'
    ],
    'Recruitment Coordinator' => [
        'Interview scheduling & candidate follow-ups',
        'Recruitment administration & documentation',
        'ATS / HRIS support',
        'Coordination with hiring managers',
        'Communication & organizational skills'
    ],
    'HR Generalist' => [
        'HR operations & administration',
        'Recruitment & onboarding support',
        'Employee relations & performance tracking',
        'Policy implementation & compliance',
        'Payroll & benefits administration'
    ],
    'Training Coordinator' => [
        'Training needs assessment',
        'Program planning & scheduling',
        'Workshop & session facilitation',
        'Learning management systems (LMS)',
        'Communication & presentation skills'
    ],
    'Learning & Development Officer' => [
        'Training program design & delivery',
        'Skill gap analysis',
        'Coaching & mentoring',
        'E-learning / LMS platforms',
        'Evaluation & feedback'
    ],
    'Talent Acquisition Officer' => [
        'Recruitment strategy & candidate sourcing',
        'Interviewing & evaluation',
        'Employer branding & networking',
        'Recruitment metrics & reporting',
        'ATS / HRIS system proficiency'
    ],
    'Compensation & Benefits Specialist' => [
        'Salary & benefits administration',
        'Payroll coordination & deductions',
        'Compensation benchmarking & analysis',
        'Regulatory compliance (labor laws, taxation)',
        'Communication & problem-solving'
    ],
    'HR Assistant' => [
        'Administrative support & record keeping',
        'Employee onboarding assistance',
        'HRIS / payroll software usage',
        'Communication & organization',
        'Scheduling & coordination'
    ],
    'HR Officer' => [
        'HR operations & administration',
        'Recruitment & onboarding support',
        'Employee relations & performance tracking',
        'HRIS / payroll & benefits administration',
        'Communication & organizational skills'
    ],
    'HR Administrator' => [
        'HR record keeping & data management',
        'Payroll & benefits support',
        'HR policy adherence',
        'Administrative coordination',
        'Communication & organizational skills'
    ],
    'Employee Relations Officer' => [
        'Conflict resolution & mediation',
        'Employee engagement & retention',
        'HR policy implementation',
        'Communication & interpersonal skills',
        'Investigation & compliance'
    ],
    'HR Business Partner' => [
        'Strategic HR consulting & alignment with business goals',
        'Workforce planning & change management',
        'Performance management support',
        'Stakeholder communication & advisory',
        'Problem-solving & decision-making'
    ],
    'HR Coordinator' => [
        'Recruitment & onboarding coordination',
        'HR administrative tasks & documentation',
        'Scheduling & communication',
        'HRIS / payroll support',
        'Organizational skills'
    ],
    'Payroll Specialist' => [
        'Payroll processing & tax compliance',
        'Salary computation & deductions',
        'Benefits administration',
        'HRIS / payroll software proficiency',
        'Attention to detail & accuracy'
    ],
    'HR Analyst' => [
        'HR data collection & analysis',
        'Metrics reporting & dashboards',
        'Process improvement recommendations',
        'HRIS / Excel & analytics tools',
        'Problem-solving & communication'
    ],
    'HR Consultant' => [
        'HR strategy & advisory',
        'Policy development & compliance',
        'Talent management & workforce planning',
        'Communication & presentation',
        'Analytical & problem-solving skills'
    ],
    'Onboarding Specialist' => [
        'New hire orientation & integration',
        'Documentation & compliance',
        'HRIS / onboarding platforms',
        'Communication & engagement',
        'Process coordination'
    ],
    
    // Manufacturing / Production
    'Production Supervisor' => [
        'Team supervision & leadership',
        'Production planning & scheduling',
        'Quality control & compliance',
        'Problem-solving & decision-making',
        'Communication & coordination'
    ],
    'Machine Operator' => [
        'Operating machinery & equipment',
        'Safety & compliance with protocols',
        'Basic troubleshooting & maintenance',
        'Monitoring production output',
        'Attention to detail'
    ],
    'Quality Control Inspector' => [
        'Product inspection & testing',
        'Knowledge of quality standards (ISO, Six Sigma)',
        'Measurement & testing tools proficiency',
        'Documentation & reporting',
        'Analytical & problem-solving skills'
    ],
    'Plant Manager' => [
        'Plant operations management',
        'Production planning & efficiency optimization',
        'Team leadership & supervision',
        'Budgeting & resource allocation',
        'Safety & regulatory compliance'
    ],
    'Production Planner' => [
        'Production scheduling & workflow optimization',
        'Resource allocation & inventory coordination',
        'Data analysis & reporting',
        'Communication & coordination with teams',
        'Problem-solving & planning'
    ],
    'Assembler' => [
        'Component assembly & fitting',
        'Reading technical drawings & instructions',
        'Hand tools & machinery operation',
        'Quality checks & compliance',
        'Attention to detail & manual dexterity'
    ],
    'Factory Worker' => [
        'Assembly line operations',
        'Equipment & tool handling',
        'Following production protocols & safety standards',
        'Teamwork & collaboration',
        'Basic quality checks'
    ],
    'Manufacturing Engineer' => [
        'Process optimization & workflow design',
        'Equipment & machinery specification',
        'Lean manufacturing & efficiency improvement',
        'Technical problem-solving',
        'Data analysis & reporting'
    ],
    'Line Supervisor' => [
        'Supervising assembly/production line',
        'Scheduling & task delegation',
        'Quality monitoring & control',
        'Team leadership & communication',
        'Problem-solving & reporting'
    ],
    'Shift Supervisor' => [
        'Shift operations management',
        'Team supervision & coordination',
        'Production tracking & reporting',
        'Quality & safety compliance',
        'Problem-solving & decision-making'
    ],
    'Inventory Controller' => [
        'Inventory tracking & management',
        'Stock reconciliation & reporting',
        'ERP / inventory software proficiency',
        'Analytical & organizational skills',
        'Coordination with production & procurement'
    ],
    'Process Operator' => [
        'Operating production processes & equipment',
        'Monitoring process parameters',
        'Troubleshooting & preventive maintenance',
        'Compliance with safety & quality standards',
        'Documentation & reporting'
    ],
    'Production Technician' => [
        'Equipment setup & operation',
        'Troubleshooting & maintenance',
        'Process monitoring & optimization',
        'Quality assurance & documentation',
        'Communication & teamwork'
    ],
    'Packaging Operator' => [
        'Packaging machinery operation',
        'Quality inspection of packaged products',
        'Compliance with safety & hygiene standards',
        'Manual dexterity & attention to detail',
        'Teamwork & productivity'
    ],
    'Production Scheduler' => [
        'Production planning & scheduling',
        'Resource allocation & workflow optimization',
        'Communication & coordination with departments',
        'Data analysis & reporting',
        'Problem-solving & time management'
    ],
    'Operations Supervisor' => [
        'Supervising overall production operations',
        'Team leadership & task delegation',
        'Quality & safety compliance',
        'Problem-solving & process improvement',
        'Reporting & coordination'
    ],
    'Plant Technician' => [
        'Equipment installation & maintenance',
        'Troubleshooting & repair',
        'Monitoring machinery & process performance',
        'Safety compliance & documentation',
        'Communication & technical reporting'
    ],
    
    // Logistics / Warehouse / Supply Chain
    'Warehouse Supervisor' => [
        'Team supervision & leadership',
        'Warehouse operations & workflow management',
        'Inventory tracking & control',
        'Safety & compliance management',
        'Communication & coordination'
    ],
    'Logistics Coordinator' => [
        'Shipment scheduling & tracking',
        'Supply chain coordination',
        'Transportation & route planning',
        'Communication with vendors & internal teams',
        'Problem-solving & organization'
    ],
    'Inventory Clerk' => [
        'Inventory monitoring & stock counting',
        'Record keeping & reporting',
        'ERP / inventory software proficiency',
        'Attention to detail & accuracy',
        'Coordination with warehouse & procurement teams'
    ],
    'Stock Controller' => [
        'Stock monitoring & reconciliation',
        'Inventory audits & reporting',
        'ERP / inventory management systems',
        'Attention to detail & organization',
        'Coordination with warehouse & procurement'
    ],
    'Supply Chain Analyst' => [
        'Data analysis & reporting',
        'Supply chain optimization & forecasting',
        'Process improvement & efficiency tracking',
        'ERP / analytics tools proficiency',
        'Problem-solving & critical thinking'
    ],
    'Shipping & Receiving Clerk' => [
        'Shipment processing & documentation',
        'Goods inspection & quality check',
        'Record keeping & reporting',
        'Coordination with carriers & warehouse teams',
        'Attention to detail & organization'
    ],
    'Transport Planner' => [
        'Route planning & optimization',
        'Vehicle scheduling & coordination',
        'Communication with drivers & logistics teams',
        'Data analysis & reporting',
        'Problem-solving & time management'
    ],
    'Procurement Officer' => [
        'Vendor sourcing & negotiation',
        'Purchase order management',
        'Cost analysis & budgeting',
        'Compliance with procurement policies',
        'Communication & negotiation skills'
    ],
    'Supply Officer' => [
        'Procurement & supply management',
        'Inventory control & replenishment',
        'Vendor coordination & negotiation',
        'Compliance & documentation',
        'Analytical & organizational skills'
    ],
    'Fleet Manager' => [
        'Vehicle & fleet maintenance',
        'Route planning & logistics coordination',
        'Compliance with safety regulations',
        'Budgeting & resource management',
        'Team management & communication'
    ],
    'Distribution Manager' => [
        'Distribution operations management',
        'Inventory & warehouse coordination',
        'Logistics & transportation planning',
        'Team leadership & supervision',
        'Performance tracking & problem-solving'
    ],
    'Order Fulfillment Officer' => [
        'Processing customer orders accurately',
        'Inventory tracking & picking/packing coordination',
        'ERP / order management software proficiency',
        'Communication & coordination with warehouse teams',
        'Attention to detail & timeliness'
    ],
    'Warehouse Staff' => [
        'Picking, packing, & stocking',
        'Equipment handling (forklifts, pallet jacks)',
        'Inventory monitoring & reporting',
        'Safety & compliance adherence',
        'Teamwork & productivity'
    ],
    'Logistics Officer' => [
        'Shipment coordination & tracking',
        'Vendor & carrier communication',
        'Documentation & record keeping',
        'Problem-solving & workflow optimization',
        'ERP / logistics software knowledge'
    ],
    'Logistics Manager' => [
        'Overall logistics & supply chain management',
        'Team leadership & coordination',
        'Performance tracking & process improvement',
        'Budgeting & resource allocation',
        'Vendor management & negotiation'
    ],
    'Delivery Coordinator' => [
        'Delivery scheduling & route planning',
        'Communication with drivers & customers',
        'Shipment tracking & reporting',
        'Problem-solving & time management',
        'Customer service & coordination'
    ],
    
    // Marketing / Sales
    'Marketing Specialist' => [
        'Market research & analysis',
        'Campaign planning & execution',
        'Content creation & copywriting',
        'Communication & presentation',
        'Digital marketing tools (SEO, Google Ads, social media)'
    ],
    'Sales Executive' => [
        'Prospecting & lead generation',
        'Negotiation & closing skills',
        'Customer relationship management (CRM tools)',
        'Product knowledge & presentation',
        'Communication & persuasion'
    ],
    'Sales Representative' => [
        'Prospecting & lead generation',
        'Negotiation & closing skills',
        'Customer relationship management (CRM tools)',
        'Product knowledge & presentation',
        'Communication & persuasion'
    ],
    'Brand Manager' => [
        'Brand strategy & positioning',
        'Marketing campaign planning',
        'Market research & consumer insights',
        'Communication & leadership',
        'Project management & budgeting'
    ],
    'Account Manager' => [
        'Client relationship management',
        'Sales & upselling strategies',
        'Account planning & reporting',
        'Communication & negotiation',
        'Problem-solving & customer service'
    ],
    'Key Account Manager' => [
        'Strategic account management',
        'Relationship building with high-value clients',
        'Sales planning & performance monitoring',
        'Negotiation & problem-solving',
        'Communication & collaboration'
    ],
    'Social Media Manager' => [
        'Social media strategy & content creation',
        'Platform management (Facebook, Instagram, LinkedIn, TikTok)',
        'Analytics & performance tracking',
        'Community engagement & communication',
        'Creativity & trend awareness'
    ],
    'Marketing Coordinator' => [
        'Campaign coordination & scheduling',
        'Content management & copywriting',
        'Market research & reporting',
        'Communication & teamwork',
        'Digital marketing tools & social media knowledge'
    ],
    'Event Marketing Coordinator' => [
        'Event planning & execution',
        'Vendor coordination & logistics',
        'Budget management & scheduling',
        'Promotion & marketing support',
        'Communication & multitasking'
    ],
    'Business Development Officer' => [
        'Lead generation & opportunity identification',
        'Market research & competitive analysis',
        'Proposal & presentation skills',
        'Negotiation & relationship-building',
        'Strategic thinking & planning'
    ],
    'Advertising Specialist' => [
        'Campaign planning & execution',
        'Media planning & buying',
        'Copywriting & creative development',
        'Analytics & performance monitoring',
        'Communication & collaboration'
    ],
    'Promotions Officer' => [
        'Campaign & promotional activity planning',
        'Coordination with marketing & sales teams',
        'Event execution & public engagement',
        'Communication & creativity',
        'Performance tracking & reporting'
    ],
    'Digital Marketing Analyst' => [
        'SEO & SEM optimization',
        'Web & social media analytics',
        'Data interpretation & reporting',
        'Campaign performance tracking',
        'Technical marketing tools (Google Analytics, Ads, CRM)'
    ],
    'Product Manager' => [
        'Product lifecycle management',
        'Market research & competitor analysis',
        'Stakeholder communication & coordination',
        'Strategic planning & decision-making',
        'Project management & roadmap creation'
    ],
    'Sales Supervisor' => [
        'Sales team leadership & coaching',
        'Performance tracking & reporting',
        'Goal setting & target achievement',
        'Customer relationship management',
        'Problem-solving & decision-making'
    ],
    'Territory Sales Manager' => [
        'Regional sales planning & execution',
        'Client relationship & territory management',
        'Market analysis & competitor tracking',
        'Team coordination & target achievement',
        'Communication & negotiation'
    ],
    'Marketing Analyst' => [
        'Market research & data analysis',
        'Consumer insights & reporting',
        'Campaign performance evaluation',
        'Presentation & communication',
        'Analytical tools (Excel, SPSS, Google Analytics)'
    ],
    
    // Creative / Media / Design
    'Graphic Designer' => [
        'Adobe Creative Suite (Photoshop, Illustrator, InDesign)',
        'Typography & layout design',
        'Branding & visual communication',
        'Creativity & conceptual thinking',
        'Attention to detail & time management'
    ],
    'Video Editor' => [
        'Video editing software (Premiere Pro, Final Cut Pro, After Effects)',
        'Storyboarding & sequencing',
        'Color grading & audio editing',
        'Creativity & attention to detail',
        'Time management & collaboration'
    ],
    'Content Creator' => [
        'Social media content development',
        'Photography & videography basics',
        'Copywriting & storytelling',
        'Creativity & audience engagement',
        'Video & graphic editing tools'
    ],
    'Art Director' => [
        'Visual concept development & design direction',
        'Leadership & team management',
        'Branding & creative strategy',
        'Project management & collaboration',
        'Creativity & critical thinking'
    ],
    'Illustrator' => [
        'Drawing & illustration skills (digital & traditional)',
        'Adobe Illustrator & Procreate',
        'Visual storytelling & conceptual design',
        'Creativity & attention to detail',
        'Time management & collaboration'
    ],
    'Photographer' => [
        'Camera operation & composition',
        'Lighting & studio techniques',
        'Photo editing software (Lightroom, Photoshop)',
        'Creativity & visual storytelling',
        'Attention to detail & project management'
    ],
    'Animator' => [
        '2D/3D animation software (After Effects, Maya, Blender)',
        'Storyboarding & motion design',
        'Creativity & artistic skills',
        'Timing & pacing',
        'Collaboration & feedback incorporation'
    ],
    'Motion Graphics Designer' => [
        'Motion graphics & animation software (After Effects, Cinema 4D)',
        'Visual storytelling & conceptualization',
        'Video editing & compositing',
        'Creativity & timing',
        'Collaboration & attention to detail'
    ],
    'Copywriter' => [
        'Writing & storytelling',
        'Marketing & branding knowledge',
        'Editing & proofreading',
        'Creativity & communication',
        'SEO & digital content understanding'
    ],
    'UX/UI Designer' => [
        'Wireframing & prototyping tools (Figma, Sketch, Adobe XD)',
        'User experience & interface design',
        'Research & user testing',
        'Visual design & interaction principles',
        'Communication & problem-solving'
    ],
    'Creative Director' => [
        'Creative strategy & brand vision',
        'Leadership & team management',
        'Project management & collaboration',
        'Visual communication & conceptual thinking',
        'Creativity & decision-making'
    ],
    'Visual Designer' => [
        'Graphic design & visual communication',
        'Branding & typography',
        'Adobe Creative Suite proficiency',
        'Attention to detail & creativity',
        'Collaboration & presentation skills'
    ],
    'Web Designer' => [
        'Web design principles & responsive design',
        'HTML/CSS basics',
        'UI/UX design knowledge',
        'Adobe XD, Figma, or Sketch proficiency',
        'Creativity & problem-solving'
    ],
    'Production Designer' => [
        'Set & production design (film, TV, theatre)',
        'Visual storytelling & concept development',
        'Drafting & layout skills',
        'Creativity & attention to detail',
        'Collaboration & project coordination'
    ],
    'Layout Artist' => [
        'Page layout & composition',
        'Typography & color theory',
        'Adobe InDesign / Illustrator proficiency',
        'Attention to detail & creativity',
        'Communication & meeting deadlines'
    ],
    
    // Construction / Infrastructure
    'Construction Manager' => [
        'Project planning & scheduling',
        'Budgeting & resource allocation',
        'Team leadership & supervision',
        'Health, safety & compliance management',
        'Communication & problem-solving'
    ],
    'Site Engineer' => [
        'Site planning & layout',
        'Construction supervision & inspection',
        'Technical drawings & specifications interpretation',
        'Quality control & compliance',
        'Problem-solving & teamwork'
    ],
    'Architect' => [
        'Design & conceptualization',
        'CAD / Revit / SketchUp proficiency',
        'Technical drawings & documentation',
        'Creativity & spatial awareness',
        'Project coordination & communication'
    ],
    'Foreman' => [
        'Supervising construction crews',
        'Task delegation & scheduling',
        'Safety & compliance enforcement',
        'Quality control & site monitoring',
        'Communication & leadership'
    ],
    'Project Manager' => [
        'Project planning & execution',
        'Budget & resource management',
        'Risk assessment & mitigation',
        'Team leadership & coordination',
        'Communication & stakeholder management'
    ],
    'Quantity Surveyor' => [
        'Cost estimation & budgeting',
        'Material take-offs & procurement',
        'Contract management & compliance',
        'Risk analysis & cost control',
        'Analytical & communication skills'
    ],
    'Civil Technician' => [
        'Drafting & technical drawing interpretation',
        'Site surveying & inspection',
        'Construction support & reporting',
        'Knowledge of materials & construction methods',
        'Teamwork & problem-solving'
    ],
    'Structural Designer' => [
        'Structural analysis & design',
        'CAD / structural design software',
        'Material & load calculations',
        'Compliance with building codes & safety standards',
        'Problem-solving & attention to detail'
    ],
    'Safety Officer' => [
        'Site safety management & inspections',
        'Risk assessment & hazard identification',
        'Compliance with OSHA / local safety regulations',
        'Emergency response planning',
        'Communication & training'
    ],
    'Building Inspector' => [
        'Site inspection & quality assessment',
        'Compliance with building codes & regulations',
        'Documentation & reporting',
        'Attention to detail & problem-solving',
        'Communication & coordination'
    ],
    'Construction Supervisor' => [
        'Supervising daily construction operations',
        'Team coordination & scheduling',
        'Quality assurance & safety compliance',
        'Reporting & problem-solving',
        'Communication & leadership'
    ],
    'Field Engineer' => [
        'On-site engineering support',
        'Construction monitoring & reporting',
        'Technical problem-solving',
        'Coordination with project teams',
        'Safety compliance & communication'
    ],
    'Project Engineer' => [
        'Project planning & technical oversight',
        'Cost control & scheduling',
        'Design & construction coordination',
        'Problem-solving & reporting',
        'Communication & stakeholder management'
    ],
    'Site Supervisor' => [
        'Site team supervision & scheduling',
        'Quality & safety compliance',
        'Daily operations monitoring',
        'Coordination with engineers & foremen',
        'Problem-solving & leadership'
    ],
    'Estimator' => [
        'Cost estimation & budgeting',
        'Material quantity calculation',
        'Tendering & proposal preparation',
        'Analytical & numerical skills',
        'Communication & reporting'
    ],
    
    // Food / Hospitality / Tourism
    'Chef' => [
        'Menu planning & recipe development',
        'Food preparation & cooking techniques',
        'Kitchen management & leadership',
        'Food safety & hygiene compliance',
        'Creativity & time management'
    ],
    'Sous Chef' => [
        'Assisting head chef in kitchen operations',
        'Supervising kitchen staff & line cooks',
        'Food preparation & quality control',
        'Inventory & stock management',
        'Communication & problem-solving'
    ],
    'Line Cook' => [
        'Cooking & food preparation on specific stations',
        'Following recipes & portion control',
        'Maintaining kitchen hygiene & safety',
        'Time management & multitasking',
        'Teamwork & communication'
    ],
    'Prep Cook' => [
        'Ingredient preparation & mise en place',
        'Cutting, chopping, and portioning',
        'Maintaining cleanliness & organization',
        'Following recipes & instructions',
        'Speed & accuracy'
    ],
    'Grill Cook' => [
        'Operating grill stations & cooking meat/fish',
        'Temperature control & timing',
        'Food quality & presentation',
        'Kitchen safety & sanitation',
        'Team coordination & efficiency'
    ],
    'Fry Cook' => [
        'Operating fryers & cooking fried foods',
        'Temperature control & timing',
        'Food safety & hygiene',
        'Speed & multitasking',
        'Teamwork & communication'
    ],
    'Breakfast Cook' => [
        'Preparing breakfast items (eggs, pancakes, etc.)',
        'Following recipes & portion control',
        'Food presentation & quality control',
        'Time management & multitasking',
        'Hygiene & teamwork'
    ],
    'Pastry / Dessert Cook' => [
        'Baking & dessert preparation',
        'Recipe following & portion control',
        'Presentation & creativity',
        'Kitchen hygiene & safety',
        'Time management & teamwork'
    ],
    'Baker' => [
        'Bread & pastry production',
        'Dough preparation & baking techniques',
        'Oven management & timing',
        'Quality control & presentation',
        'Hygiene & attention to detail'
    ],
    'Barista' => [
        'Coffee & beverage preparation',
        'Machine operation & maintenance',
        'Customer service & communication',
        'Cleanliness & hygiene',
        'Speed & multitasking'
    ],
    'Crew Member' => [
        'Food preparation & assembly',
        'Customer service & order taking',
        'Cleanliness & hygiene compliance',
        'Teamwork & coordination',
        'Operating kitchen & service equipment'
    ],
    'Fast Food Crew' => [
        'Food preparation & assembly',
        'Customer service & order taking',
        'Cleanliness & hygiene compliance',
        'Teamwork & coordination',
        'Operating kitchen & service equipment'
    ],
    'Restaurant Manager' => [
        'Staff supervision & scheduling',
        'Customer service management',
        'Inventory & supply management',
        'Financial & operational oversight',
        'Problem-solving & conflict resolution'
    ],
    'Kitchen Staff' => [
        'Food preparation & cleaning',
        'Supporting cooks & chefs',
        'Maintaining kitchen hygiene & safety',
        'Equipment handling & maintenance',
        'Teamwork & coordination'
    ],
    'Shift Supervisor' => [
        'Supervising staff during shifts',
        'Ensuring service quality & safety compliance',
        'Task delegation & workflow management',
        'Problem-solving & customer service',
        'Reporting & documentation'
    ],
    'Cashier' => [
        'Handling payments & POS systems',
        'Customer service & communication',
        'Accuracy & attention to detail',
        'Handling cash & financial transactions',
        'Problem-solving & efficiency'
    ],
    'Host / Hostess' => [
        'Greeting & seating customers',
        'Reservation management',
        'Customer service & communication',
        'Multitasking & organizational skills',
        'Problem-solving & coordination'
    ],
    'Food Runner' => [
        'Delivering food to tables promptly',
        'Coordination with kitchen & waitstaff',
        'Maintaining cleanliness & presentation',
        'Customer service & communication',
        'Efficiency & time management'
    ],
    'Waiter / Waitress' => [
        'Taking orders & serving food',
        'Customer service & communication',
        'Menu knowledge & upselling',
        'Multitasking & organization',
        'Hygiene & teamwork'
    ],
    'Bartender' => [
        'Drink preparation & mixology',
        'Customer interaction & service',
        'Inventory & stock management',
        'Hygiene & compliance',
        'Speed, multitasking & creativity'
    ],
    'Hotel Front Desk Officer' => [
        'Check-in & check-out procedures',
        'Reservation management',
        'Customer service & problem-solving',
        'Communication & multitasking',
        'Billing & record keeping'
    ],
    'Concierge' => [
        'Guest assistance & personalized service',
        'Booking & travel arrangements',
        'Local knowledge & recommendations',
        'Communication & problem-solving',
        'Professionalism & multitasking'
    ],
    'Tour Guide' => [
        'Guiding & presenting information to guests',
        'Communication & public speaking',
        'Local history & cultural knowledge',
        'Customer service & engagement',
        'Time management & planning'
    ],
    'Event Coordinator' => [
        'Event planning & organization',
        'Vendor coordination & scheduling',
        'Budgeting & logistics management',
        'Communication & problem-solving',
        'Creativity & attention to detail'
    ],
    'Catering Staff' => [
        'Food preparation & service',
        'Setup & presentation of catering events',
        'Hygiene & safety compliance',
        'Customer service & teamwork',
        'Efficiency & reliability'
    ],
    
    // Retail / Sales Operations
    'Store Manager' => [
        'Team leadership & staff supervision',
        'Sales target achievement & performance tracking',
        'Inventory & stock management',
        'Customer service & complaint resolution',
        'Budgeting & operational planning'
    ],
    'Assistant Store Manager' => [
        'Supporting store operations & management',
        'Staff supervision & training',
        'Inventory & sales monitoring',
        'Customer service & problem-solving',
        'Communication & operational planning'
    ],
    'Sales Associate' => [
        'Customer service & communication',
        'Product knowledge & recommendation',
        'Sales & upselling techniques',
        'Cash handling & POS operation',
        'Teamwork & reliability'
    ],
    'Sales Representative' => [
        'Prospecting & lead generation',
        'Product knowledge & presentations',
        'Sales target achievement',
        'Customer relationship management',
        'Communication & negotiation'
    ],
    'Retail Sales Officer' => [
        'Sales target monitoring & achievement',
        'Customer service & product guidance',
        'Upselling & promotions',
        'Inventory support',
        'Communication & teamwork'
    ],
    'Merchandiser' => [
        'Product placement & visual merchandising',
        'Stock rotation & inventory management',
        'Sales analysis & reporting',
        'Attention to detail & creativity',
        'Coordination with store management'
    ],
    'Visual Merchandiser' => [
        'Store layout & product display design',
        'Creativity & aesthetic sense',
        'Brand consistency & promotional setup',
        'Inventory coordination',
        'Communication & teamwork'
    ],
    'Cashier' => [
        'Cash handling & POS system operation',
        'Customer service & communication',
        'Accuracy & attention to detail',
        'Basic math & record keeping',
        'Efficiency & professionalism'
    ],
    'Retail Supervisor' => [
        'Staff supervision & scheduling',
        'Sales target monitoring',
        'Customer service & problem-solving',
        'Inventory & operational oversight',
        'Communication & leadership'
    ],
    'Floor Manager' => [
        'Supervision of floor staff & operations',
        'Customer service & complaint resolution',
        'Sales performance monitoring',
        'Visual merchandising oversight',
        'Coordination & communication'
    ],
    'Stock Clerk' => [
        'Receiving, storing, & organizing stock',
        'Inventory tracking & reporting',
        'Stock rotation & quality control',
        'Teamwork & efficiency',
        'Attention to detail'
    ],
    'Inventory Clerk' => [
        'Inventory monitoring & reconciliation',
        'Stock audits & reporting',
        'ERP / inventory system usage',
        'Attention to detail & accuracy',
        'Coordination with store & warehouse teams'
    ],
    'Sales Coordinator' => [
        'Sales order processing & tracking',
        'Customer communication & support',
        'Coordination with sales team & management',
        'Reporting & documentation',
        'Organizational & multitasking skills'
    ],
    'Customer Service Associate' => [
        'Customer support & problem-solving',
        'Communication & interpersonal skills',
        'Product knowledge & guidance',
        'Record keeping & reporting',
        'Patience & professionalism'
    ],
    'Key Account Executive' => [
        'Managing major client accounts',
        'Sales strategy & target achievement',
        'Relationship building & negotiation',
        'Reporting & coordination',
        'Communication & analytical skills'
    ],
    'Shop Attendant' => [
        'Customer service & assistance',
        'Stock organization & shelf arrangement',
        'Cash handling & POS operation',
        'Cleanliness & hygiene maintenance',
        'Teamwork & reliability'
    ],
    'Display Coordinator' => [
        'Product display & visual merchandising',
        'Creativity & attention to detail',
        'Coordination with merchandising & marketing teams',
        'Store layout & promotional setup',
        'Communication & teamwork'
    ],
    
    // Transportation
    'Driver' => [
        'Safe driving & traffic law compliance',
        'Vehicle operation & maintenance',
        'Route planning & navigation',
        'Time management & punctuality',
        'Customer service & communication'
    ],
    'Delivery Rider' => [
        'Safe motorcycle/bike operation',
        'Route planning & timely delivery',
        'Package handling & documentation',
        'Customer service & communication',
        'Navigation & problem-solving'
    ],
    'Fleet Manager' => [
        'Vehicle & fleet management',
        'Maintenance scheduling & oversight',
        'Route planning & logistics coordination',
        'Budgeting & cost control',
        'Team management & communication'
    ],
    'Transport Coordinator' => [
        'Scheduling & dispatching vehicles',
        'Route optimization & tracking',
        'Coordination with drivers & clients',
        'Record keeping & reporting',
        'Problem-solving & multitasking'
    ],
    'Logistics Driver' => [
        'Safe operation of delivery vehicles',
        'Loading & unloading procedures',
        'Route planning & delivery tracking',
        'Compliance with transportation regulations',
        'Customer service & communication'
    ],
    'Bus Driver' => [
        'Passenger safety & driving compliance',
        'Vehicle inspection & maintenance',
        'Route adherence & time management',
        'Customer service & communication',
        'Emergency response skills'
    ],
    'Taxi Driver' => [
        'Safe driving & traffic law compliance',
        'Navigation & route optimization',
        'Customer service & interpersonal skills',
        'Fare handling & record keeping',
        'Problem-solving & punctuality'
    ],
    'Air Cargo Handler' => [
        'Handling & loading cargo safely',
        'Equipment operation (forklifts, pallet jacks)',
        'Documentation & inventory tracking',
        'Compliance with aviation safety regulations',
        'Teamwork & efficiency'
    ],
    'Dispatch Officer' => [
        'Scheduling & coordinating deliveries',
        'Communication with drivers & clients',
        'Monitoring fleet movement & performance',
        'Problem-solving & multitasking',
        'Record keeping & reporting'
    ],
    'Vehicle Inspector' => [
        'Vehicle inspection & diagnostics',
        'Maintenance scheduling & compliance checks',
        'Safety & regulatory adherence',
        'Attention to detail & reporting',
        'Technical problem-solving'
    ],
    'Truck Driver' => [
        'Safe operation of heavy vehicles',
        'Route planning & delivery scheduling',
        'Vehicle maintenance & safety checks',
        'Documentation & compliance',
        'Customer service & communication'
    ],
    'Shuttle Driver' => [
        'Passenger safety & timely transport',
        'Vehicle operation & maintenance',
        'Route adherence & scheduling',
        'Customer service & communication',
        'Emergency response & problem-solving'
    ],
    'Transportation Officer' => [
        'Transport planning & coordination',
        'Fleet & vehicle management',
        'Compliance with safety & regulations',
        'Record keeping & reporting',
        'Communication & problem-solving'
    ],
    'Delivery Supervisor' => [
        'Supervising delivery staff & operations',
        'Route planning & scheduling',
        'Monitoring delivery performance & compliance',
        'Customer service & problem-solving',
        'Communication & team coordination'
    ],
    
    // Law Enforcement / Criminology
    'Police Officer' => [
        'Law enforcement & public safety',
        'Patrolling & incident response',
        'Conflict resolution & communication',
        'Report writing & documentation',
        'Physical fitness & situational awareness'
    ],
    'Detective' => [
        'Criminal investigation & evidence collection',
        'Interviewing & interrogation techniques',
        'Case analysis & report writing',
        'Critical thinking & problem-solving',
        'Discretion & ethical judgment'
    ],
    'Crime Scene Investigator' => [
        'Evidence collection & preservation',
        'Forensic analysis & documentation',
        'Photography & scene mapping',
        'Knowledge of forensic protocols',
        'Attention to detail & analytical skills'
    ],
    'Security Analyst' => [
        'Threat assessment & risk management',
        'Surveillance & monitoring',
        'Cybersecurity or physical security knowledge',
        'Report writing & incident documentation',
        'Analytical & problem-solving skills'
    ],
    'Forensic Specialist' => [
        'Laboratory testing & analysis',
        'Evidence handling & chain of custody',
        'Knowledge of forensic techniques (DNA, fingerprinting, etc.)',
        'Report preparation & documentation',
        'Attention to detail & analytical thinking'
    ],
    'Corrections Officer' => [
        'Inmate supervision & safety enforcement',
        'Conflict resolution & crisis management',
        'Documentation & reporting',
        'Security protocol adherence',
        'Communication & physical fitness'
    ],
    'Crime Analyst' => [
        'Data collection & crime trend analysis',
        'Statistical & analytical skills',
        'Reporting & visualization',
        'Knowledge of law enforcement databases',
        'Critical thinking & problem-solving'
    ],
    'Intelligence Officer' => [
        'Information gathering & analysis',
        'Risk assessment & threat evaluation',
        'Report writing & briefing skills',
        'Critical thinking & discretion',
        'Communication & coordination with agencies'
    ],
    'Patrol Officer' => [
        'Patrolling & law enforcement',
        'Emergency response & first aid',
        'Conflict management & communication',
        'Observation & reporting skills',
        'Physical fitness & situational awareness'
    ],
    'Investigation Officer' => [
        'Case investigation & evidence gathering',
        'Interviewing witnesses & suspects',
        'Report writing & documentation',
        'Analytical & problem-solving skills',
        'Discretion & ethical judgment'
    ],
    'Police Chief' => [
        'Department leadership & management',
        'Strategic planning & policy implementation',
        'Crisis management & decision-making',
        'Communication & stakeholder engagement',
        'Supervisory & team leadership'
    ],
    'Detective Sergeant' => [
        'Leading investigation teams',
        'Case management & supervision',
        'Mentoring & training junior officers',
        'Conflict resolution & decision-making',
        'Analytical & investigative skills'
    ],
    'Crime Prevention Officer' => [
        'Community engagement & education',
        'Crime prevention program planning',
        'Risk assessment & threat mitigation',
        'Communication & interpersonal skills',
        'Reporting & documentation'
    ],
    'Forensic Analyst' => [
        'Laboratory testing & evidence analysis',
        'Knowledge of forensic tools & methods',
        'Data interpretation & reporting',
        'Attention to detail & analytical thinking',
        'Compliance with legal & safety protocols'
    ],
    
    // Security Services
    'Security Guard' => [
        'Patrolling & surveillance',
        'Access control & monitoring',
        'Emergency response & first aid',
        'Report writing & documentation',
        'Communication & situational awareness'
    ],
    'Security Supervisor' => [
        'Supervising security personnel',
        'Scheduling & task delegation',
        'Incident response & investigation',
        'Communication & team coordination',
        'Compliance with safety protocols'
    ],
    'Loss Prevention Officer' => [
        'Theft prevention & monitoring',
        'Risk assessment & vulnerability analysis',
        'Investigating incidents & reporting',
        'Customer service & conflict resolution',
        'Attention to detail & integrity'
    ],
    'Bodyguard' => [
        'Personal protection & threat assessment',
        'Close protection techniques & situational awareness',
        'Emergency response & evacuation planning',
        'Physical fitness & defensive tactics',
        'Discretion & communication'
    ],
    'Security Coordinator' => [
        'Security planning & scheduling',
        'Team coordination & task management',
        'Risk assessment & mitigation',
        'Reporting & documentation',
        'Communication & problem-solving'
    ],
    'Alarm Systems Officer' => [
        'Monitoring alarm systems & alerts',
        'Responding to security breaches',
        'Equipment operation & troubleshooting',
        'Reporting & documentation',
        'Attention to detail & technical knowledge'
    ],
    'CCTV Operator' => [
        'Operating CCTV & surveillance systems',
        'Monitoring for suspicious activity',
        'Incident reporting & documentation',
        'Attention to detail & focus',
        'Communication & coordination with security teams'
    ],
    'Security Consultant' => [
        'Risk assessment & security planning',
        'Policy development & compliance',
        'Security audits & recommendations',
        'Communication & presentation skills',
        'Analytical & problem-solving skills'
    ],
    'Executive Protection Officer' => [
        'Personal protection & threat assessment',
        'Close protection & situational awareness',
        'Risk mitigation & emergency response',
        'Communication & discretion',
        'Physical fitness & defensive tactics'
    ],
    'Event Security Officer' => [
        'Crowd management & access control',
        'Monitoring & patrolling event premises',
        'Emergency response & incident reporting',
        'Communication & teamwork',
        'Conflict resolution & situational awareness'
    ],
    'Security Officer' => [
        'Access control & patrolling',
        'Surveillance & monitoring',
        'Emergency response & reporting',
        'Compliance with safety protocols',
        'Communication & situational awareness'
    ],
    'Security Manager' => [
        'Leading security teams & operations',
        'Risk assessment & mitigation planning',
        'Policy development & compliance',
        'Incident investigation & reporting',
        'Communication & team leadership'
    ],
    'Safety and Security Officer' => [
        'Workplace safety & security monitoring',
        'Risk assessment & emergency planning',
        'Compliance with safety regulations',
        'Incident reporting & documentation',
        'Communication & problem-solving'
    ],
    
    // Skilled / Technical (TESDA)
    'Electrician' => [
        'Electrical installation & wiring',
        'Troubleshooting & repair',
        'Knowledge of electrical codes & safety regulations',
        'Reading blueprints & technical diagrams',
        'Problem-solving & attention to detail'
    ],
    'Welder' => [
        'Welding techniques (MIG, TIG, Stick, etc.)',
        'Metal fabrication & assembly',
        'Reading technical drawings & blueprints',
        'Safety & protective equipment usage',
        'Precision & attention to detail'
    ],
    'Automotive Technician' => [
        'Vehicle diagnostics & repair',
        'Engine & electrical systems troubleshooting',
        'Maintenance & service procedures',
        'Knowledge of automotive tools & equipment',
        'Problem-solving & attention to detail'
    ],
    'Carpenter' => [
        'Woodworking & furniture construction',
        'Reading blueprints & technical drawings',
        'Measuring, cutting & assembling materials',
        'Use of hand & power tools',
        'Attention to detail & craftsmanship'
    ],
    'Plumber' => [
        'Installation & repair of pipes & fixtures',
        'Reading technical diagrams & blueprints',
        'Knowledge of plumbing codes & safety regulations',
        'Troubleshooting & maintenance',
        'Problem-solving & efficiency'
    ],
    'Mason' => [
        'Bricklaying, blockwork, & concrete work',
        'Reading construction plans & specifications',
        'Mixing & applying building materials',
        'Safety & site compliance',
        'Precision & teamwork'
    ],
    'HVAC Technician' => [
        'Installation & maintenance of HVAC systems',
        'Troubleshooting heating, cooling, and ventilation equipment',
        'Electrical & mechanical knowledge',
        'Safety & compliance with standards',
        'Customer service & communication'
    ],
    'CNC Operator' => [
        'Operating CNC machines',
        'Reading technical drawings & G-code',
        'Machine setup & maintenance',
        'Precision measurement & quality control',
        'Problem-solving & attention to detail'
    ],
    'Industrial Technician' => [
        'Maintenance & repair of industrial equipment',
        'Mechanical & electrical troubleshooting',
        'Preventive maintenance procedures',
        'Safety & compliance knowledge',
        'Technical documentation & reporting'
    ],
    'Electronics Technician' => [
        'Circuit analysis & repair',
        'Testing & troubleshooting electronic equipment',
        'Reading schematics & technical diagrams',
        'Soldering & assembly skills',
        'Attention to detail & problem-solving'
    ],
    'Refrigeration Technician' => [
        'Installation & repair of refrigeration systems',
        'Electrical & mechanical troubleshooting',
        'Safety & environmental compliance',
        'Preventive maintenance & testing',
        'Customer service & technical reporting'
    ],
    'Machinist' => [
        'Operating lathes, mills, and other machining tools',
        'Reading technical drawings & specifications',
        'Precision measurement & quality control',
        'Material selection & tooling knowledge',
        'Problem-solving & manual dexterity'
    ],
    'Fabricator' => [
        'Metal fabrication & assembly',
        'Welding & cutting techniques',
        'Reading technical drawings & blueprints',
        'Equipment & tool operation',
        'Safety compliance & precision'
    ],
    'Pipefitter' => [
        'Installing & repairing piping systems',
        'Reading technical drawings & schematics',
        'Welding, cutting, & fitting pipes',
        'Safety compliance & pressure testing',
        'Problem-solving & teamwork'
    ],
    'Maintenance Technician' => [
        'Equipment maintenance & troubleshooting',
        'Mechanical & electrical repair',
        'Preventive maintenance scheduling',
        'Safety & compliance knowledge',
        'Problem-solving & documentation'
    ],
    'Tool and Die Maker' => [
        'Designing & creating molds, dies, and tools',
        'Precision machining & metalworking',
        'Reading blueprints & technical drawings',
        'Quality control & measurement',
        'Problem-solving & attention to detail'
    ],
    
    // Agriculture / Fisheries
    'Farm Manager' => [
        'Farm operations planning & management',
        'Staff supervision & coordination',
        'Crop & livestock management',
        'Budgeting & resource allocation',
        'Problem-solving & decision-making'
    ],
    'Agronomist' => [
        'Crop science & soil management',
        'Fertilizer & pest management',
        'Field research & data analysis',
        'Report writing & documentation',
        'Communication & advisory skills'
    ],
    'Fishery Technician' => [
        'Aquaculture & fish farm management',
        'Water quality monitoring',
        'Feeding & breeding programs',
        'Equipment operation & maintenance',
        'Record keeping & reporting'
    ],
    'Agricultural Laborer' => [
        'Planting, harvesting & basic crop care',
        'Operating basic farm equipment',
        'Irrigation & soil preparation',
        'Manual labor & physical stamina',
        'Following instructions & teamwork'
    ],
    'Crop Specialist' => [
        'Crop management & pest control',
        'Soil testing & nutrient management',
        'Crop monitoring & reporting',
        'Research & advisory services',
        'Analytical & problem-solving skills'
    ],
    'Livestock Technician' => [
        'Animal care & feeding',
        'Health monitoring & vaccination',
        'Breeding & herd management',
        'Record keeping & reporting',
        'Safety & hygiene compliance'
    ],
    'Farm Equipment Operator' => [
        'Operating tractors, harvesters, and machinery',
        'Basic maintenance & troubleshooting',
        'Field preparation & cultivation',
        'Safety & compliance',
        'Time management & efficiency'
    ],
    'Agriculture Extension Officer' => [
        'Advising farmers on best practices',
        'Conducting training & workshops',
        'Data collection & reporting',
        'Communication & public engagement',
        'Problem-solving & advisory skills'
    ],
    'Horticulturist' => [
        'Plant cultivation & garden management',
        'Pest & disease management',
        'Soil & nutrient analysis',
        'Landscape design & plant selection',
        'Observation & documentation'
    ],
    'Aquaculture Specialist' => [
        'Fish & aquatic species management',
        'Water quality & environmental monitoring',
        'Feeding, breeding & health management',
        'Record keeping & reporting',
        'Technical & analytical skills'
    ],
    'Plantation Supervisor' => [
        'Supervising plantation operations & staff',
        'Crop production monitoring',
        'Inventory & resource management',
        'Compliance with safety & environmental regulations',
        'Communication & team coordination'
    ],
    'Farm Inspector' => [
        'Inspecting farms for compliance & quality',
        'Assessing crop & livestock conditions',
        'Reporting & documentation',
        'Knowledge of agricultural regulations',
        'Attention to detail & analytical skills'
    ],
    'Soil Scientist' => [
        'Soil analysis & testing',
        'Nutrient management & recommendations',
        'Research & field studies',
        'Reporting & data documentation',
        'Problem-solving & analytical skills'
    ],
    'Agriculture Technician' => [
        'Assisting with crop & livestock management',
        'Equipment operation & maintenance',
        'Field data collection & reporting',
        'Pest & disease monitoring',
        'Teamwork & technical skills'
    ],
    
    // Freelance / Online / Remote
    'Virtual Assistant' => [
        'Administrative support & scheduling',
        'Email & calendar management',
        'Data entry & document preparation',
        'Communication & professionalism',
        'Time management & multitasking'
    ],
    'Freelance Writer' => [
        'Writing & editing skills',
        'Research & content creation',
        'SEO & digital content knowledge',
        'Meeting deadlines & time management',
        'Communication & adaptability'
    ],
    'Online Tutor' => [
        'Subject matter expertise',
        'Lesson planning & curriculum delivery',
        'Virtual teaching tools (Zoom, Google Meet, LMS)',
        'Communication & patience',
        'Feedback & performance tracking'
    ],
    'Graphic Designer (Remote)' => [
        'Adobe Creative Suite / Figma / Canva',
        'Branding & visual communication',
        'Creativity & conceptual thinking',
        'Time management & meeting deadlines',
        'Communication & client collaboration'
    ],
    'Content Creator' => [
        'Social media content development',
        'Video/graphic editing skills',
        'Storytelling & creativity',
        'Audience engagement & analytics',
        'Time management & self-discipline'
    ],
    'Social Media Manager' => [
        'Social media strategy & planning',
        'Content creation & scheduling tools',
        'Analytics & performance tracking',
        'Communication & customer engagement',
        'Creativity & trend awareness'
    ],
    'Web Developer' => [
        'HTML, CSS, JavaScript & frameworks',
        'Responsive design & UX/UI knowledge',
        'Debugging & problem-solving',
        'Version control & collaboration tools (Git)',
        'Communication & project coordination'
    ],
    'Data Entry Specialist' => [
        'Accurate data input & verification',
        'Spreadsheet & database management',
        'Attention to detail & accuracy',
        'Time management & efficiency',
        'Basic computer literacy'
    ],
    'Translator' => [
        'Fluency in source & target languages',
        'Grammar & writing accuracy',
        'Cultural understanding & localization',
        'Time management & deadlines',
        'Communication & research skills'
    ],
    'Remote Customer Support' => [
        'Customer service & problem-solving',
        'Communication via email, chat, or call',
        'CRM & support software proficiency',
        'Patience & empathy',
        'Time management & multitasking'
    ],
    'Online Consultant' => [
        'Subject matter expertise',
        'Client communication & advisory skills',
        'Virtual presentation & collaboration tools',
        'Problem-solving & strategic planning',
        'Time management & self-discipline'
    ],
    'SEO Specialist' => [
        'SEO strategy & keyword research',
        'Google Analytics & Search Console',
        'Content optimization & link building',
        'Reporting & performance tracking',
        'Communication & adaptability'
    ],
    'Digital Marketing Freelancer' => [
        'Social media marketing & ads management',
        'Content creation & SEO knowledge',
        'Analytics & performance tracking',
        'Client communication & reporting',
        'Creativity & time management'
    ],
    'Video Editor – Remote' => [
        'Video editing software (Premiere, Final Cut, After Effects)',
        'Storyboarding & sequencing',
        'Color correction & sound editing',
        'Creativity & attention to detail',
        'Meeting deadlines & client communication'
    ],
    
    // Legal / Government / Public Service
    'Lawyer' => [
        'Legal research & analysis',
        'Drafting contracts & legal documents',
        'Court representation & litigation',
        'Negotiation & advocacy skills',
        'Critical thinking & problem-solving'
    ],
    'Paralegal' => [
        'Legal research & case preparation',
        'Drafting & reviewing documents',
        'Knowledge of legal procedures & terminology',
        'Organization & record keeping',
        'Communication & attention to detail'
    ],
    'Government Officer' => [
        'Policy implementation & regulatory compliance',
        'Administrative & operational planning',
        'Public service & stakeholder coordination',
        'Report writing & documentation',
        'Communication & problem-solving'
    ],
    'Legal Assistant' => [
        'Document preparation & filing',
        'Case research & organization',
        'Scheduling & administrative support',
        'Communication & teamwork',
        'Attention to detail & confidentiality'
    ],
    'Policy Analyst' => [
        'Policy research & evaluation',
        'Data analysis & report writing',
        'Strategic thinking & problem-solving',
        'Communication & stakeholder engagement',
        'Knowledge of public policy frameworks'
    ],
    'Court Clerk' => [
        'Case filing & document management',
        'Scheduling hearings & court events',
        'Knowledge of court procedures & regulations',
        'Attention to detail & record keeping',
        'Communication & organizational skills'
    ],
    'Compliance Officer' => [
        'Regulatory & legal compliance monitoring',
        'Policy development & implementation',
        'Risk assessment & mitigation',
        'Reporting & documentation',
        'Analytical thinking & attention to detail'
    ],
    'Public Administrator' => [
        'Administrative & organizational management',
        'Policy implementation & evaluation',
        'Budgeting & resource management',
        'Communication & leadership',
        'Problem-solving & decision-making'
    ],
    'Legal Researcher' => [
        'Conducting case law & statutory research',
        'Drafting legal memoranda & reports',
        'Analytical & critical thinking',
        'Attention to detail & documentation',
        'Communication & collaboration'
    ],
    'Legal Consultant' => [
        'Advising clients on legal matters',
        'Risk assessment & compliance guidance',
        'Contract review & negotiation',
        'Research & analytical skills',
        'Communication & professionalism'
    ],
    'Judicial Clerk' => [
        'Assisting judges with case research & drafting',
        'Legal document preparation',
        'Court procedure knowledge',
        'Analytical & research skills',
        'Confidentiality & attention to detail'
    ],
    'Public Policy Officer' => [
        'Policy development & evaluation',
        'Research & data analysis',
        'Stakeholder engagement & communication',
        'Project management & planning',
        'Strategic thinking & problem-solving'
    ],
    'Court Officer' => [
        'Maintaining court security & order',
        'Managing courtroom procedures',
        'Knowledge of legal protocols',
        'Communication & interpersonal skills',
        'Attention to detail & reliability'
    ],
    'Administrative Law Officer' => [
        'Reviewing regulations & administrative laws',
        'Compliance monitoring & enforcement',
        'Legal research & documentation',
        'Analytical thinking & problem-solving',
        'Communication & report writing'
    ],
    
    // Maritime / Aviation / Transport Specialized
    'Ship Captain' => [
        'Navigation & ship handling',
        'Crew management & leadership',
        'Safety & emergency procedures',
        'Voyage planning & logistics',
        'Communication & decision-making'
    ],
    'Pilot' => [
        'Aircraft operation & flight navigation',
        'Safety protocols & emergency handling',
        'Flight planning & weather assessment',
        'Communication with control towers & crew',
        'Decision-making & situational awareness'
    ],
    'Flight Attendant' => [
        'Passenger safety & emergency procedures',
        'Customer service & communication',
        'In-flight service & hospitality',
        'Conflict resolution & teamwork',
        'First aid & safety compliance'
    ],
    'Marine Engineer' => [
        'Ship machinery & propulsion systems maintenance',
        'Technical troubleshooting & repair',
        'Safety & compliance with maritime regulations',
        'Equipment monitoring & documentation',
        'Team coordination & problem-solving'
    ],
    'Deck Officer' => [
        'Navigation & ship deck operations',
        'Cargo handling & stowage planning',
        'Safety & emergency drills',
        'Communication with crew & port authorities',
        'Record keeping & compliance'
    ],
    'Air Traffic Controller' => [
        'Aircraft monitoring & coordination',
        'Airspace management & communication',
        'Problem-solving & quick decision-making',
        'Safety & emergency handling',
        'Attention to detail & situational awareness'
    ],
    'Ship Engineer' => [
        'Maintenance of ship engines & technical systems',
        'Troubleshooting & repair',
        'Compliance with maritime safety regulations',
        'Monitoring performance & documentation',
        'Team coordination & problem-solving'
    ],
    'Cabin Crew' => [
        'Passenger safety & assistance',
        'In-flight service & hospitality',
        'Communication & conflict resolution',
        'Emergency response & first aid',
        'Teamwork & professionalism'
    ],
    'Marine Technician' => [
        'Maintenance & repair of marine equipment',
        'Technical troubleshooting & inspections',
        'Safety & compliance with regulations',
        'Documentation & reporting',
        'Team coordination & problem-solving'
    ],
    'Aviation Safety Officer' => [
        'Safety policy development & compliance',
        'Risk assessment & hazard identification',
        'Safety audits & reporting',
        'Emergency preparedness & training',
        'Communication & coordination'
    ],
    'Port Officer' => [
        'Port operations management',
        'Cargo & vessel documentation',
        'Safety & regulatory compliance',
        'Coordination with shipping agents & authorities',
        'Communication & problem-solving'
    ],
    'Harbor Master' => [
        'Supervision of port & harbor operations',
        'Vessel traffic management & navigation safety',
        'Compliance with maritime laws & regulations',
        'Coordination with port staff & authorities',
        'Decision-making & leadership'
    ],
    'Flight Dispatcher' => [
        'Flight planning & route coordination',
        'Monitoring weather & air traffic',
        'Communication with pilots & ground control',
        'Safety compliance & emergency planning',
        'Analytical thinking & decision-making'
    ],
    
    // Science / Research / Environment
    'Research Scientist' => [
        'Experimental design & methodology',
        'Data collection & analysis',
        'Scientific writing & reporting',
        'Critical thinking & problem-solving',
        'Laboratory & field research techniques'
    ],
    'Laboratory Technician' => [
        'Sample preparation & testing',
        'Equipment operation & maintenance',
        'Recording & documenting results',
        'Quality control & compliance with protocols',
        'Attention to detail & accuracy'
    ],
    'Environmental Officer' => [
        'Environmental monitoring & assessment',
        'Compliance with environmental regulations',
        'Data collection & reporting',
        'Risk assessment & mitigation',
        'Communication & stakeholder coordination'
    ],
    'Data Analyst' => [
        'Data collection & cleaning',
        'Statistical analysis & interpretation',
        'Visualization & reporting tools (Excel, Tableau, Python, R)',
        'Problem-solving & decision support',
        'Communication of findings to stakeholders'
    ],
    'Biochemist' => [
        'Conducting biochemical experiments',
        'Laboratory techniques & instrumentation',
        'Data analysis & interpretation',
        'Report writing & documentation',
        'Attention to detail & critical thinking'
    ],
    'Ecologist' => [
        'Field research & ecological surveys',
        'Environmental data collection & analysis',
        'Species & habitat monitoring',
        'Report writing & scientific documentation',
        'Analytical & observational skills'
    ],
    'Field Researcher' => [
        'Conducting surveys & experiments in the field',
        'Data collection & recording',
        'Equipment handling & sample preservation',
        'Observation & analytical skills',
        'Teamwork & communication'
    ],
    'Microbiologist' => [
        'Culturing & analyzing microorganisms',
        'Laboratory safety & sterilization protocols',
        'Data analysis & experimental documentation',
        'Use of laboratory instruments & techniques',
        'Attention to detail & critical thinking'
    ],
    'Environmental Consultant' => [
        'Environmental assessment & reporting',
        'Regulatory compliance & advisory services',
        'Risk analysis & mitigation planning',
        'Project management & coordination',
        'Communication & technical writing'
    ],
    'Lab Assistant' => [
        'Supporting laboratory experiments',
        'Preparing samples & reagents',
        'Equipment cleaning & maintenance',
        'Record keeping & documentation',
        'Following instructions & safety protocols'
    ],
    'Research Assistant' => [
        'Assisting with experimental design & execution',
        'Data collection & analysis',
        'Literature review & documentation',
        'Laboratory or field support',
        'Teamwork & organizational skills'
    ],
    'Marine Biologist' => [
        'Studying marine ecosystems & species',
        'Field research & sample collection',
        'Laboratory analysis & data interpretation',
        'Report writing & presentation',
        'Observational & analytical skills'
    ],
    'Laboratory Analyst' => [
        'Performing laboratory tests & assays',
        'Quality control & compliance',
        'Data analysis & reporting',
        'Operating lab equipment',
        'Attention to detail & problem-solving'
    ],
    'Climate Scientist' => [
        'Climate data collection & modeling',
        'Environmental & atmospheric research',
        'Statistical & computational analysis',
        'Report writing & policy recommendation',
        'Critical thinking & scientific communication'
    ],
    
    // Arts / Entertainment / Culture
    'Actor' => [
        'Acting & performance techniques',
        'Script memorization & interpretation',
        'Emotional expression & body language',
        'Collaboration & teamwork',
        'Communication & adaptability'
    ],
    'Musician' => [
        'Instrument proficiency or vocal skills',
        'Music theory & composition',
        'Performance & stage presence',
        'Collaboration & ensemble work',
        'Creativity & practice discipline'
    ],
    'Dancer' => [
        'Dance technique & choreography execution',
        'Physical fitness & flexibility',
        'Stage presence & performance skills',
        'Teamwork & collaboration',
        'Discipline & practice commitment'
    ],
    'Cultural Program Coordinator' => [
        'Event planning & organization',
        'Cultural knowledge & program design',
        'Communication & stakeholder engagement',
        'Budgeting & resource management',
        'Leadership & team coordination'
    ],
    'Singer' => [
        'Vocal technique & control',
        'Music interpretation & performance',
        'Stage presence & audience engagement',
        'Collaboration & rehearsals',
        'Discipline & practice'
    ],
    'Director' => [
        'Creative vision & storytelling',
        'Team leadership & coordination',
        'Script analysis & interpretation',
        'Communication & problem-solving',
        'Project management & scheduling'
    ],
    'Photographer' => [
        'Camera operation & photography techniques',
        'Composition & lighting skills',
        'Photo editing & post-processing',
        'Creativity & artistic vision',
        'Communication & client coordination'
    ],
    'Art Curator' => [
        'Art history knowledge & research',
        'Exhibition planning & design',
        'Collection management & documentation',
        'Communication & public engagement',
        'Attention to detail & organizational skills'
    ],
    'Theater Performer' => [
        'Acting & performance skills',
        'Stage presence & voice projection',
        'Memorization & improvisation',
        'Teamwork & collaboration',
        'Discipline & rehearsal commitment'
    ],
    'Costume Designer' => [
        'Fashion & costume design',
        'Sewing & garment construction',
        'Creativity & concept development',
        'Collaboration with directors & performers',
        'Time management & project planning'
    ],
    'Visual Artist' => [
        'Drawing, painting, or sculpting skills',
        'Creativity & artistic expression',
        'Knowledge of art materials & techniques',
        'Portfolio development & presentation',
        'Attention to detail & self-discipline'
    ],
    'Film Editor' => [
        'Video editing software proficiency (Premiere, Final Cut, DaVinci)',
        'Storytelling & narrative pacing',
        'Attention to detail & visual continuity',
        'Collaboration with directors & production teams',
        'Problem-solving & creative thinking'
    ],
    'Choreographer' => [
        'Dance composition & choreography design',
        'Leadership & teaching dancers',
        'Music interpretation & timing',
        'Creativity & artistic vision',
        'Communication & teamwork'
    ],
    'Stage Manager' => [
        'Event & production planning',
        'Team coordination & scheduling',
        'Communication with performers & crew',
        'Problem-solving & adaptability',
        'Attention to detail & organizational skills'
    ],
    
    // Religion / NGO / Development / Cooperative
    'Pastor' => [
        'Spiritual leadership & counseling',
        'Public speaking & preaching',
        'Community engagement & mentorship',
        'Conflict resolution & pastoral care',
        'Organizational & administrative skills'
    ],
    'NGO Program Officer' => [
        'Program planning & implementation',
        'Project monitoring & evaluation',
        'Community engagement & stakeholder coordination',
        'Reporting & documentation',
        'Communication & problem-solving'
    ],
    'Social Worker' => [
        'Case assessment & client counseling',
        'Crisis intervention & support',
        'Advocacy & resource coordination',
        'Communication & empathy',
        'Documentation & reporting'
    ],
    'Community Organizer' => [
        'Community engagement & mobilization',
        'Advocacy & program planning',
        'Public speaking & facilitation',
        'Leadership & networking',
        'Problem-solving & coordination'
    ],
    'Missionary' => [
        'Community outreach & engagement',
        'Spiritual guidance & mentorship',
        'Cross-cultural communication & adaptability',
        'Program coordination & organization',
        'Empathy & interpersonal skills'
    ],
    'Development Officer' => [
        'Fundraising & donor relations',
        'Program planning & implementation',
        'Stakeholder engagement & networking',
        'Monitoring & reporting',
        'Communication & strategic thinking'
    ],
    'Volunteer Coordinator' => [
        'Recruitment & training of volunteers',
        'Scheduling & task delegation',
        'Communication & motivation',
        'Program coordination & support',
        'Organizational & record-keeping skills'
    ],
    'Church Administrator' => [
        'Administrative & organizational management',
        'Event planning & coordination',
        'Communication & stakeholder engagement',
        'Financial management & budgeting',
        'Leadership & problem-solving'
    ],
    'Program Manager' => [
        'Program design & implementation',
        'Team management & coordination',
        'Monitoring & evaluation',
        'Budgeting & resource allocation',
        'Communication & reporting'
    ],
    'Cooperative Manager' => [
        'Cooperative operations & management',
        'Financial & resource management',
        'Team leadership & coordination',
        'Communication & member relations',
        'Problem-solving & decision-making'
    ],
    'Field Officer – NGO' => [
        'Community outreach & engagement',
        'Program implementation & monitoring',
        'Data collection & reporting',
        'Communication & problem-solving',
        'Teamwork & coordination'
    ],
    'Project Officer – NGO' => [
        'Project planning & execution',
        'Stakeholder engagement & reporting',
        'Monitoring & evaluation',
        'Communication & documentation',
        'Problem-solving & adaptability'
    ],
    'Community Development Officer' => [
        'Needs assessment & program planning',
        'Community mobilization & engagement',
        'Monitoring & reporting',
        'Communication & facilitation',
        'Problem-solving & teamwork'
    ],
    
    // Special / Rare Jobs
    'Ethical Hacker' => [
        'Penetration testing & vulnerability assessment',
        'Network & system security analysis',
        'Knowledge of cybersecurity tools & protocols',
        'Problem-solving & analytical thinking',
        'Ethical standards & reporting'
    ],
    'Stunt Performer' => [
        'Physical fitness & agility',
        'Stage & camera coordination',
        'Risk assessment & safety compliance',
        'Acting & performance skills',
        'Teamwork & adaptability'
    ],
    'Ice Sculptor' => [
        'Sculpture & carving techniques',
        'Artistic design & creativity',
        'Precision & attention to detail',
        'Tool & equipment handling',
        'Time management & project planning'
    ],
    'Professional Gamer' => [
        'Gaming strategy & mechanics',
        'Hand-eye coordination & reflexes',
        'Team communication & coordination',
        'Streaming & content creation skills',
        'Adaptability & mental focus'
    ],
    'Escape Room Designer' => [
        'Puzzle design & game mechanics',
        'Creativity & thematic storytelling',
        'Project planning & execution',
        'Problem-solving & analytical skills',
        'Collaboration & user experience design'
    ],
    'Drone Operator' => [
        'Drone piloting & navigation',
        'Aerial photography/videography',
        'Equipment maintenance & safety compliance',
        'Spatial awareness & technical troubleshooting',
        'Regulatory knowledge & reporting'
    ],
    'Voice Actor' => [
        'Vocal control & modulation',
        'Script interpretation & character development',
        'Recording & audio editing software proficiency',
        'Creativity & performance skills',
        'Communication & timing'
    ],
    'Extreme Sports Instructor' => [
        'Sport-specific technical skills',
        'Safety & risk assessment',
        'Instruction & coaching abilities',
        'Physical fitness & endurance',
        'Communication & motivation'
    ],
    'Special Effects Artist' => [
        'Visual effects design & execution',
        'Makeup, prosthetics, or CGI skills',
        'Creativity & artistic design',
        'Collaboration with production teams',
        'Technical proficiency & problem-solving'
    ],
    'Magician' => [
        'Sleight of hand & performance techniques',
        'Creativity & show design',
        'Audience engagement & stage presence',
        'Practice & precision',
        'Communication & improvisation'
    ],
    'Mystery Shopper' => [
        'Observation & reporting skills',
        'Analytical thinking & evaluation',
        'Discretion & attention to detail',
        'Communication & documentation',
        'Time management & reliability'
    ],
    'Puppeteer' => [
        'Manipulation & control of puppets',
        'Acting & storytelling',
        'Voice modulation & performance',
        'Creativity & artistic expression',
        'Coordination & teamwork'
    ],
    'Forensic Artist' => [
        'Facial reconstruction & sketching',
        'Observation & attention to detail',
        'Knowledge of anatomy & proportions',
        'Communication with law enforcement',
        'Analytical & technical drawing skills'
    ],
    
    // Utilities / Public Services
    'Electrician' => [
        'Electrical installation, wiring & repair',
        'Knowledge of electrical codes & safety regulations',
        'Troubleshooting & problem-solving',
        'Reading blueprints & technical diagrams',
        'Use of hand & power tools'
    ],
    'Water Plant Operator' => [
        'Monitoring & operating water treatment systems',
        'Chemical dosing & water quality testing',
        'Equipment operation & maintenance',
        'Compliance with environmental & safety standards',
        'Data recording & reporting'
    ],
    'Utility Technician' => [
        'Maintenance & repair of utility systems',
        'Troubleshooting mechanical & electrical issues',
        'Equipment operation & monitoring',
        'Safety & regulatory compliance',
        'Record keeping & reporting'
    ],
    'Meter Reader' => [
        'Reading utility meters accurately',
        'Data collection & entry',
        'Attention to detail & reliability',
        'Basic knowledge of electrical/water systems',
        'Communication & reporting'
    ],
    'Waste Management Officer' => [
        'Waste collection & disposal management',
        'Recycling & environmental compliance',
        'Equipment operation & safety procedures',
        'Monitoring & reporting',
        'Coordination & teamwork'
    ],
    'Line Worker' => [
        'Electrical line installation & maintenance',
        'Troubleshooting & repair',
        'Safety & compliance with regulations',
        'Physical fitness & use of specialized tools',
        'Team coordination & problem-solving'
    ],
    'Public Utility Engineer' => [
        'Design & maintenance of public utility systems',
        'Project planning & implementation',
        'Technical analysis & troubleshooting',
        'Compliance with safety & environmental regulations',
        'Communication & documentation'
    ],
    'Maintenance Technician' => [
        'Preventive & corrective maintenance',
        'Equipment repair & troubleshooting',
        'Mechanical & electrical knowledge',
        'Safety compliance & risk management',
        'Record keeping & reporting'
    ],
    'Facility Officer' => [
        'Facility operations & maintenance management',
        'Scheduling & supervising maintenance tasks',
        'Safety & compliance monitoring',
        'Vendor & contractor coordination',
        'Documentation & reporting'
    ],
    'Energy Technician' => [
        'Monitoring & maintenance of energy systems',
        'Equipment operation & troubleshooting',
        'Safety & regulatory compliance',
        'Data recording & reporting',
        'Problem-solving & technical skills'
    ],
    'Water Treatment Technician' => [
        'Operating water treatment equipment',
        'Monitoring water quality & chemical dosing',
        'Maintenance & troubleshooting',
        'Compliance with safety & environmental standards',
        'Record keeping & reporting'
    ],
    'Power Plant Operator' => [
        'Operating turbines, generators & plant systems',
        'Monitoring performance & safety systems',
        'Troubleshooting & preventive maintenance',
        'Compliance with regulations & safety standards',
        'Communication & teamwork'
    ],
    
    // Telecommunications
    'Telecommunications Technician' => [
        'Installation, maintenance & repair of telecom equipment',
        'Troubleshooting & problem-solving',
        'Knowledge of network systems & protocols',
        'Use of hand and diagnostic tools',
        'Safety & compliance with regulations'
    ],
    'Network Engineer' => [
        'Network design, configuration & optimization',
        'Troubleshooting & performance monitoring',
        'Knowledge of routers, switches, firewalls',
        'Network security & protocols (TCP/IP, VPNs, etc.)',
        'Documentation & technical reporting'
    ],
    'Customer Support Specialist' => [
        'Technical support & issue resolution',
        'Knowledge of telecom products & services',
        'Communication & problem-solving skills',
        'CRM software proficiency',
        'Patience & customer service orientation'
    ],
    'Field Engineer' => [
        'On-site installation & maintenance of telecom systems',
        'Equipment troubleshooting & repair',
        'Technical documentation & reporting',
        'Coordination with operations teams',
        'Safety & regulatory compliance'
    ],
    'Tower Technician' => [
        'Installation & maintenance of telecom towers',
        'Rigging & climbing safety procedures',
        'Equipment calibration & troubleshooting',
        'Team coordination & physical fitness',
        'Documentation & compliance'
    ],
    'Telecom Analyst' => [
        'Network monitoring & performance analysis',
        'Data interpretation & reporting',
        'Knowledge of telecom infrastructure & protocols',
        'Problem-solving & optimization',
        'Communication & documentation'
    ],
    'Fiber Optic Technician' => [
        'Fiber optic cable installation & splicing',
        'Troubleshooting & signal testing',
        'Equipment handling & calibration',
        'Safety & compliance with standards',
        'Documentation & reporting'
    ],
    'VoIP Specialist' => [
        'Installation & configuration of VoIP systems',
        'Network troubleshooting & voice quality monitoring',
        'Knowledge of SIP, codecs, and PBX systems',
        'Problem-solving & technical support',
        'Documentation & communication'
    ],
    'RF Engineer' => [
        'Radio frequency design & analysis',
        'Signal testing & optimization',
        'Knowledge of antennas, transmitters, and spectrum regulations',
        'Problem-solving & technical documentation',
        'Collaboration with network teams'
    ],
    'Service Coordinator' => [
        'Scheduling & coordinating service requests',
        'Customer communication & follow-up',
        'Monitoring field operations',
        'Documentation & reporting',
        'Problem-solving & multitasking'
    ],
    'Telecom Sales Officer' => [
        'Knowledge of telecom products & services',
        'Customer relationship management',
        'Sales strategy & target achievement',
        'Communication & negotiation skills',
        'Market analysis & reporting'
    ],
    'Network Installation Technician' => [
        'Installation & configuration of network hardware',
        'Cable management & connectivity testing',
        'Troubleshooting & technical problem-solving',
        'Knowledge of LAN/WAN & network protocols',
        'Documentation & reporting'
    ],
    
    // Mining / Geology
    'Geologist' => [
        'Geological mapping & field surveys',
        'Mineral & rock analysis',
        'Data collection & interpretation',
        'Report writing & documentation',
        'Knowledge of environmental & safety regulations'
    ],
    'Mining Engineer' => [
        'Mine design & planning',
        'Equipment selection & operations oversight',
        'Safety & regulatory compliance',
        'Cost estimation & resource management',
        'Problem-solving & project coordination'
    ],
    'Drill Operator' => [
        'Operation of drilling machinery & equipment',
        'Drilling techniques & procedures',
        'Maintenance & troubleshooting of equipment',
        'Safety compliance & risk awareness',
        'Recording & reporting of drilling data'
    ],
    'Safety Officer' => [
        'Site safety management & inspections',
        'Risk assessment & hazard identification',
        'Compliance with mining regulations & safety protocols',
        'Emergency response planning',
        'Training & communication'
    ],
    'Surveyor' => [
        'Land & mine surveying techniques',
        'Use of surveying instruments (total station, GPS)',
        'Data analysis & mapping',
        'Reporting & documentation',
        'Team coordination & compliance with standards'
    ],
    'Mine Technician' => [
        'Equipment operation & maintenance',
        'Monitoring mining processes',
        'Data collection & reporting',
        'Safety compliance & problem-solving',
        'Technical support for mining operations'
    ],
    'Geotechnical Engineer' => [
        'Soil & rock mechanics analysis',
        'Site investigation & sampling',
        'Slope stability & foundation assessment',
        'Data interpretation & reporting',
        'Safety & regulatory compliance'
    ],
    'Mineral Analyst' => [
        'Mineral sampling & testing',
        'Laboratory analysis techniques',
        'Data recording & interpretation',
        'Report preparation & documentation',
        'Knowledge of environmental & safety standards'
    ],
    'Exploration Officer' => [
        'Planning & conducting mineral exploration activities',
        'Geological surveys & data collection',
        'Sampling & site assessment',
        'Reporting & mapping',
        'Compliance with safety & environmental regulations'
    ],
    'Quarry Supervisor' => [
        'Supervision of quarry operations',
        'Equipment & workforce management',
        'Safety & compliance enforcement',
        'Production monitoring & reporting',
        'Problem-solving & coordination'
    ],
    'Mine Surveyor' => [
        'Mine mapping & surveying',
        'Use of surveying instruments & software',
        'Data collection & analysis',
        'Reporting & documentation',
        'Safety compliance & teamwork'
    ],
    'Mining Safety Engineer' => [
        'Risk assessment & hazard mitigation',
        'Development of safety protocols & procedures',
        'Compliance with mining regulations & standards',
        'Safety audits & inspections',
        'Training & communication'
    ],
    
    // Oil / Gas / Energy
    'Petroleum Engineer' => [
        'Oil & gas reservoir evaluation & planning',
        'Drilling & production optimization',
        'Data analysis & simulation',
        'Project management & cost estimation',
        'Safety & regulatory compliance'
    ],
    'Safety Officer' => [
        'Site safety management & inspections',
        'Risk assessment & hazard identification',
        'Compliance with industry safety standards (OSHA, HSE)',
        'Emergency response planning',
        'Training & communication'
    ],
    'Energy Analyst' => [
        'Energy data collection & analysis',
        'Market & consumption trend analysis',
        'Reporting & visualization (Excel, Power BI, etc.)',
        'Regulatory compliance awareness',
        'Problem-solving & strategic recommendations'
    ],
    'Plant Operator' => [
        'Operation of oil, gas, or energy plant equipment',
        'Monitoring system performance & safety',
        'Troubleshooting & preventive maintenance',
        'Compliance with operational & environmental standards',
        'Reporting & record keeping'
    ],
    'Drilling Engineer' => [
        'Drilling planning & execution',
        'Equipment selection & operations oversight',
        'Drilling optimization & cost management',
        'Safety & regulatory compliance',
        'Data analysis & reporting'
    ],
    'Maintenance Technician' => [
        'Preventive & corrective maintenance of plant machinery',
        'Equipment troubleshooting & repair',
        'Mechanical & electrical knowledge',
        'Safety compliance & risk management',
        'Documentation & reporting'
    ],
    'Field Operator' => [
        'On-site operation of oil & gas equipment',
        'Monitoring and maintenance',
        'Safety & regulatory compliance',
        'Equipment troubleshooting',
        'Data collection & reporting'
    ],
    'Pipeline Engineer' => [
        'Design, installation & maintenance of pipelines',
        'Pressure & flow analysis',
        'Safety & regulatory compliance',
        'Project management & coordination',
        'Technical documentation & reporting'
    ],
    'Energy Consultant' => [
        'Advisory on energy efficiency & optimization',
        'Data analysis & market research',
        'Regulatory & compliance guidance',
        'Project planning & recommendations',
        'Communication & stakeholder management'
    ],
    'Refinery Technician' => [
        'Operation & maintenance of refinery equipment',
        'Monitoring process parameters & safety systems',
        'Troubleshooting & preventive maintenance',
        'Compliance with safety & environmental standards',
        'Reporting & documentation'
    ],
    'Production Engineer – Oil & Gas' => [
        'Production optimization & monitoring',
        'Equipment & process troubleshooting',
        'Data analysis & reporting',
        'Safety & compliance with operational standards',
        'Coordination with operations & maintenance teams'
    ],
    'Offshore Rig Technician' => [
        'Operation & maintenance of offshore rig equipment',
        'Safety compliance & emergency preparedness',
        'Equipment troubleshooting & repair',
        'Monitoring & reporting operational parameters',
        'Team coordination & problem-solving'
    ],
    
    // Chemical / Industrial
    'Chemical Engineer' => [
        'Chemical process design & optimization',
        'Equipment operation & troubleshooting',
        'Safety & regulatory compliance (OSHA, HSE)',
        'Process simulation & analysis',
        'Project management & documentation'
    ],
    'Laboratory Technician' => [
        'Sample preparation & chemical testing',
        'Equipment operation & calibration',
        'Data recording & analysis',
        'Safety compliance & chemical handling',
        'Reporting & documentation'
    ],
    'Process Operator' => [
        'Monitoring & controlling chemical processes',
        'Equipment operation & adjustment',
        'Safety & compliance with process standards',
        'Troubleshooting & preventive maintenance',
        'Data collection & reporting'
    ],
    'Quality Analyst' => [
        'Quality control testing & inspections',
        'Data analysis & reporting',
        'Compliance with industry standards & regulations',
        'Problem-solving & process improvement',
        'Documentation & record keeping'
    ],
    'Production Chemist' => [
        'Formulation & chemical production',
        'Process monitoring & optimization',
        'Safety & compliance with chemical handling protocols',
        'Data recording & analysis',
        'Reporting & documentation'
    ],
    'Industrial Technician' => [
        'Maintenance & repair of industrial equipment',
        'Equipment monitoring & troubleshooting',
        'Safety & regulatory compliance',
        'Process support & technical assistance',
        'Record keeping & reporting'
    ],
    'Safety Officer' => [
        'Risk assessment & hazard identification',
        'Safety protocol development & compliance',
        'Emergency response planning',
        'Training & communication',
        'Documentation & reporting'
    ],
    'Formulation Specialist' => [
        'Development of chemical formulations',
        'Laboratory testing & optimization',
        'Knowledge of chemical properties & compatibility',
        'Safety & regulatory compliance',
        'Documentation & reporting'
    ],
    'Research Chemist' => [
        'Experimental design & chemical analysis',
        'Laboratory techniques & instrumentation',
        'Data analysis & interpretation',
        'Safety compliance & chemical handling',
        'Scientific reporting & documentation'
    ],
    'Control Room Operator' => [
        'Monitoring & controlling industrial processes',
        'Equipment operation & troubleshooting',
        'Safety & regulatory compliance',
        'Data recording & reporting',
        'Problem-solving & communication'
    ],
    'Plant Chemist' => [
        'Chemical process monitoring & optimization',
        'Laboratory testing & analysis',
        'Equipment operation & safety compliance',
        'Data collection & reporting',
        'Collaboration with production teams'
    ],
    'Industrial Safety Officer' => [
        'Workplace safety management & inspections',
        'Risk assessment & mitigation',
        'Compliance with industrial regulations',
        'Emergency response planning',
        'Communication & training'
    ],
    
    // Allied Health / Special Education / Therapy
    'Physical Therapist' => [
        'Patient assessment & diagnosis',
        'Exercise prescription & rehabilitation planning',
        'Manual therapy & physical modalities',
        'Patient education & motivation',
        'Documentation & progress tracking'
    ],
    'Occupational Therapist' => [
        'Functional assessment & activity analysis',
        'Rehabilitation & adaptive technique planning',
        'Patient training in daily activities',
        'Communication & patient counseling',
        'Documentation & reporting'
    ],
    'Speech Therapist' => [
        'Speech, language, & communication assessment',
        'Therapy planning & intervention',
        'Use of assistive technologies',
        'Patient & family education',
        'Documentation & progress reporting'
    ],
    'Special Educator' => [
        'Individualized education plan (IEP) development',
        'Adapted teaching & learning strategies',
        'Behavior management & support',
        'Communication & collaboration with parents & staff',
        'Assessment & reporting'
    ],
    'Rehabilitation Specialist' => [
        'Patient evaluation & goal setting',
        'Therapeutic intervention planning',
        'Multidisciplinary collaboration',
        'Monitoring & reporting progress',
        'Patient education & support'
    ],
    'Psychologist' => [
        'Psychological assessment & testing',
        'Counseling & therapy techniques',
        'Behavioral analysis & intervention planning',
        'Research & data analysis',
        'Communication & documentation'
    ],
    'Audiologist' => [
        'Hearing assessment & diagnosis',
        'Hearing aid fitting & management',
        'Patient counseling & education',
        'Use of audiology equipment & technology',
        'Documentation & reporting'
    ],
    'Orthotist' => [
        'Design & fitting of orthotic devices',
        'Patient assessment & evaluation',
        'Equipment adjustment & troubleshooting',
        'Knowledge of anatomy & biomechanics',
        'Documentation & progress tracking'
    ],
    'Prosthetist' => [
        'Design & fitting of prosthetic devices',
        'Patient assessment & evaluation',
        'Equipment adjustment & troubleshooting',
        'Knowledge of anatomy & biomechanics',
        'Documentation & patient education'
    ],
    'Behavioral Therapist' => [
        'Behavioral assessment & intervention planning',
        'Therapy implementation & monitoring',
        'Patient & family training',
        'Data collection & analysis',
        'Documentation & reporting'
    ],
    'Therapy Assistant' => [
        'Assisting therapists in rehabilitation sessions',
        'Equipment preparation & handling',
        'Patient support & monitoring',
        'Documentation & reporting',
        'Communication & teamwork'
    ],
    'Learning Support Officer' => [
        'Supporting students with learning difficulties',
        'Implementing individualized learning plans',
        'Collaboration with teachers & therapists',
        'Monitoring progress & reporting',
        'Communication & advocacy'
    ],
    
    // Sports / Fitness / Recreation
    'Fitness Trainer' => [
        'Exercise program design & instruction',
        'Client assessment & fitness evaluation',
        'Motivation & coaching skills',
        'Knowledge of anatomy & physiology',
        'Safety & injury prevention'
    ],
    'Coach' => [
        'Team management & leadership',
        'Skill development & performance analysis',
        'Motivation & communication',
        'Strategic planning & game tactics',
        'Assessment & feedback'
    ],
    'Sports Analyst' => [
        'Data collection & performance analysis',
        'Statistical & analytical skills',
        'Knowledge of sports rules & strategies',
        'Reporting & presentation',
        'Problem-solving & strategic insights'
    ],
    'Recreation Coordinator' => [
        'Planning & organizing recreational activities',
        'Event coordination & scheduling',
        'Communication & group facilitation',
        'Safety & risk management',
        'Budgeting & resource allocation'
    ],
    'Gym Instructor' => [
        'Fitness assessment & exercise guidance',
        'Instruction on equipment use',
        'Motivation & client engagement',
        'Knowledge of anatomy & safety protocols',
        'Monitoring & reporting client progress'
    ],
    'Yoga Instructor' => [
        'Yoga practice & technique instruction',
        'Client assessment & personalized guidance',
        'Communication & motivation',
        'Safety & injury prevention',
        'Mindfulness & wellness coaching'
    ],
    'Athletic Trainer' => [
        'Injury prevention & rehabilitation',
        'Performance assessment & conditioning',
        'Emergency response & first aid',
        'Coaching & motivation',
        'Record keeping & progress tracking'
    ],
    'Sports Official' => [
        'Knowledge of rules & regulations',
        'Game monitoring & decision-making',
        'Communication & conflict resolution',
        'Observational & analytical skills',
        'Reporting & record keeping'
    ],
    'Lifeguard' => [
        'Water safety & rescue skills',
        'First aid & CPR certification',
        'Monitoring & surveillance',
        'Emergency response & communication',
        'Physical fitness & alertness'
    ],
    'Wellness Coach' => [
        'Lifestyle & wellness assessment',
        'Health & fitness guidance',
        'Motivation & behavior change strategies',
        'Communication & counseling skills',
        'Monitoring & progress tracking'
    ],
    'Personal Trainer' => [
        'Exercise program design & instruction',
        'Client assessment & fitness evaluation',
        'Motivation & coaching skills',
        'Knowledge of anatomy & physiology',
        'Safety & injury prevention'
    ],
    'Sports Physiotherapist' => [
        'Injury assessment & rehabilitation planning',
        'Therapeutic exercise prescription',
        'Manual therapy & modalities',
        'Patient education & motivation',
        'Documentation & progress tracking'
    ],
    
    // Fashion / Apparel / Beauty
    'Fashion Designer' => [
        'Clothing & accessory design',
        'Creativity & trend forecasting',
        'Pattern making & garment construction',
        'Technical drawing & CAD proficiency',
        'Fabric & material knowledge'
    ],
    'Stylist' => [
        'Personal styling & wardrobe selection',
        'Trend awareness & fashion knowledge',
        'Communication & client consultation',
        'Color coordination & outfit coordination',
        'Time management & organization'
    ],
    'Makeup Artist' => [
        'Makeup application & technique',
        'Skin & cosmetic knowledge',
        'Creativity & aesthetic sense',
        'Client consultation & personalization',
        'Hygiene & safety compliance'
    ],
    'Boutique Manager' => [
        'Retail management & merchandising',
        'Staff supervision & customer service',
        'Inventory management & ordering',
        'Sales & marketing strategies',
        'Financial & operational reporting'
    ],
    'Hairdresser' => [
        'Hair cutting, styling & coloring',
        'Knowledge of hair care & products',
        'Client consultation & personalization',
        'Hygiene & safety standards',
        'Creativity & trend awareness'
    ],
    'Fashion Merchandiser' => [
        'Trend analysis & product selection',
        'Inventory planning & stock management',
        'Retail display & visual merchandising',
        'Sales & marketing coordination',
        'Data analysis & reporting'
    ],
    'Nail Technician' => [
        'Manicure & pedicure techniques',
        'Nail art & design',
        'Hygiene & safety compliance',
        'Customer consultation & care',
        'Time management & precision'
    ],
    'Costume Designer' => [
        'Conceptual design for performances/productions',
        'Fabric selection & garment construction',
        'Collaboration with directors/stylists',
        'Creativity & technical drawing',
        'Project management & budgeting'
    ],
    'Wardrobe Consultant' => [
        'Personal wardrobe assessment',
        'Outfit coordination & styling advice',
        'Trend awareness & fashion knowledge',
        'Client communication & relationship management',
        'Organization & time management'
    ],
    'Beauty Therapist' => [
        'Skincare treatments & procedures',
        'Knowledge of beauty products & techniques',
        'Client consultation & personalization',
        'Hygiene & safety standards',
        'Customer service & communication'
    ],
    'Fashion Illustrator' => [
        'Artistic drawing & sketching skills',
        'Knowledge of fabrics & garment construction',
        'Creativity & conceptual design',
        'Digital illustration & CAD proficiency',
        'Attention to detail & presentation'
    ],
    'Image Consultant' => [
        'Personal branding & styling advice',
        'Wardrobe planning & color coordination',
        'Communication & client assessment',
        'Trend awareness & fashion knowledge',
        'Professional coaching & confidence building'
    ],
    
    // Home / Personal Services
    'Housekeeper' => [
        'Cleaning & sanitation of rooms and facilities',
        'Organization & time management',
        'Use of cleaning equipment & chemicals safely',
        'Attention to detail',
        'Customer service & discretion'
    ],
    'Nanny' => [
        'Childcare & supervision',
        'Meal preparation & feeding',
        'Activity planning & educational support',
        'Safety & emergency response',
        'Communication with parents & reporting'
    ],
    'Babysitter' => [
        'Childcare & supervision',
        'Meal preparation & feeding',
        'Activity planning & educational support',
        'Safety & emergency response',
        'Communication with parents & reporting'
    ],
    'Caregiver' => [
        'Personal care assistance (bathing, grooming, feeding)',
        'Monitoring health & medication adherence',
        'Mobility support & safety supervision',
        'Compassion & interpersonal communication',
        'Documentation & reporting of care activities'
    ],
    'Elderly Care Assistant' => [
        'Personal care assistance (bathing, grooming, feeding)',
        'Monitoring health & medication adherence',
        'Mobility support & safety supervision',
        'Compassion & interpersonal communication',
        'Documentation & reporting of care activities'
    ],
    'Home Care Aide' => [
        'Personal care assistance (bathing, grooming, feeding)',
        'Monitoring health & medication adherence',
        'Mobility support & safety supervision',
        'Compassion & interpersonal communication',
        'Documentation & reporting of care activities'
    ],
    'Personal Trainer' => [
        'Exercise program design & instruction',
        'Fitness assessment & monitoring',
        'Motivation & coaching skills',
        'Knowledge of anatomy & physiology',
        'Safety & injury prevention'
    ],
    'Driver' => [
        'Safe vehicle operation & navigation',
        'Knowledge of traffic rules & regulations',
        'Vehicle maintenance & inspection',
        'Time management & punctuality',
        'Communication & customer service'
    ],
    'Gardener' => [
        'Plant care & landscaping',
        'Pruning, planting, and maintenance',
        'Use of gardening tools & equipment',
        'Knowledge of soil, fertilizers, and irrigation',
        'Safety & environmental awareness'
    ],
    'Pet Groomer' => [
        'Animal grooming techniques (bathing, trimming, styling)',
        'Knowledge of animal behavior & safety',
        'Customer communication & service',
        'Equipment handling & maintenance',
        'Attention to detail & hygiene'
    ],
    'Laundry Attendant' => [
        'Washing, drying, and ironing clothes',
        'Knowledge of fabrics & cleaning techniques',
        'Equipment handling & maintenance',
        'Organization & attention to detail',
        'Customer service & efficiency'
    ],
    'Personal Assistant – Household' => [
        'Scheduling & household management',
        'Coordination of domestic staff & activities',
        'Communication & task delegation',
        'Budgeting & supply management',
        'Discretion & confidentiality'
    ],
    
    // Insurance / Risk / Banking
    'Insurance Agent' => [
        'Client acquisition & relationship management',
        'Knowledge of insurance products & policies',
        'Risk assessment & coverage recommendation',
        'Communication & negotiation skills',
        'Documentation & compliance'
    ],
    'Risk Analyst' => [
        'Risk identification & assessment',
        'Data analysis & modeling',
        'Financial & market research',
        'Reporting & documentation',
        'Problem-solving & decision-making'
    ],
    'Loan Officer' => [
        'Credit evaluation & loan processing',
        'Customer consultation & assessment',
        'Knowledge of banking & financial regulations',
        'Documentation & reporting',
        'Communication & problem-solving'
    ],
    'Banking Teller' => [
        'Cash handling & transaction processing',
        'Customer service & communication',
        'Accuracy & attention to detail',
        'Knowledge of banking procedures',
        'Problem-solving & basic financial advising'
    ],
    'Claims Adjuster' => [
        'Investigation & assessment of insurance claims',
        'Client communication & negotiation',
        'Knowledge of policy terms & legal requirements',
        'Documentation & reporting',
        'Analytical & problem-solving skills'
    ],
    'Underwriter' => [
        'Risk assessment & evaluation',
        'Policy review & approval',
        'Financial analysis & decision-making',
        'Compliance with regulations & standards',
        'Documentation & reporting'
    ],
    'Financial Advisor' => [
        'Financial planning & investment advice',
        'Client relationship management',
        'Knowledge of investment products & regulations',
        'Analytical & problem-solving skills',
        'Documentation & reporting'
    ],
    'Credit Analyst' => [
        'Credit evaluation & analysis',
        'Financial statement interpretation',
        'Risk assessment & reporting',
        'Decision-making & recommendation',
        'Communication & documentation'
    ],
    'Investment Officer' => [
        'Portfolio management & investment strategy',
        'Financial analysis & research',
        'Client consultation & reporting',
        'Market trend evaluation',
        'Risk management & compliance'
    ],
    'Policy Consultant' => [
        'Assessment & recommendation of insurance policies',
        'Client consultation & advisory',
        'Knowledge of policy terms & regulations',
        'Risk analysis & documentation',
        'Communication & reporting'
    ],
    'Branch Banking Officer' => [
        'Customer service & relationship management',
        'Banking operations & administration',
        'Staff supervision & coordination',
        'Compliance & regulatory knowledge',
        'Problem-solving & reporting'
    ],
    'Insurance Underwriting Assistant' => [
        'Supporting underwriters in risk assessment',
        'Data collection & documentation',
        'Policy review & compliance support',
        'Communication & coordination',
        'Analytical & organizational skills'
    ],
    
    // Micro Jobs / Informal / Daily Wage Jobs
    'Delivery Rider' => [
        'Safe vehicle operation & navigation',
        'Time management & punctuality',
        'Route planning & traffic awareness',
        'Customer service & communication',
        'Vehicle maintenance & inspection'
    ],
    'Driver' => [
        'Safe vehicle operation & navigation',
        'Time management & punctuality',
        'Route planning & traffic awareness',
        'Customer service & communication',
        'Vehicle maintenance & inspection'
    ],
    'Vendor' => [
        'Sales & customer service skills',
        'Product presentation & merchandising',
        'Cash handling & transaction management',
        'Inventory management & stock replenishment',
        'Communication & negotiation skills'
    ],
    'Market Seller' => [
        'Sales & customer service skills',
        'Product presentation & merchandising',
        'Cash handling & transaction management',
        'Inventory management & stock replenishment',
        'Communication & negotiation skills'
    ],
    'Food Cart Vendor' => [
        'Sales & customer service skills',
        'Product presentation & merchandising',
        'Cash handling & transaction management',
        'Inventory management & stock replenishment',
        'Communication & negotiation skills'
    ],
    'Street Cleaner' => [
        'Physical stamina & endurance',
        'Knowledge of cleaning/construction tools & equipment',
        'Safety & hazard awareness',
        'Teamwork & coordination',
        'Time management & task completion'
    ],
    'Helper' => [
        'Physical stamina & endurance',
        'Knowledge of cleaning/construction tools & equipment',
        'Safety & hazard awareness',
        'Teamwork & coordination',
        'Time management & task completion'
    ],
    'Construction Laborer' => [
        'Physical stamina & endurance',
        'Knowledge of cleaning/construction tools & equipment',
        'Safety & hazard awareness',
        'Teamwork & coordination',
        'Time management & task completion'
    ],
    'Day Laborer' => [
        'Physical stamina & endurance',
        'Knowledge of cleaning/construction tools & equipment',
        'Safety & hazard awareness',
        'Teamwork & coordination',
        'Time management & task completion'
    ],
    'Messenger' => [
        'Time management & punctuality',
        'Navigation & route planning',
        'Communication & reliability',
        'Task prioritization & organization',
        'Customer service & problem-solving'
    ],
    'Errand Runner' => [
        'Time management & punctuality',
        'Navigation & route planning',
        'Communication & reliability',
        'Task prioritization & organization',
        'Customer service & problem-solving'
    ],
    'Gig Worker' => [
        'Flexibility & adaptability',
        'Time management & self-motivation',
        'Task-specific skills depending on gig (e.g., delivery, labor, digital work)',
        'Customer service & communication',
        'Problem-solving & reliability'
    ],
    
    // Real Estate / Property
    'Real Estate Agent' => [
        'Client relationship management',
        'Property marketing & sales',
        'Negotiation & closing deals',
        'Market knowledge & property valuation',
        'Communication & presentation skills'
    ],
    'Sales Executive' => [
        'Client relationship management',
        'Property marketing & sales',
        'Negotiation & closing deals',
        'Market knowledge & property valuation',
        'Communication & presentation skills'
    ],
    'Broker' => [
        'Client relationship management',
        'Property marketing & sales',
        'Negotiation & closing deals',
        'Market knowledge & property valuation',
        'Communication & presentation skills'
    ],
    'Property Manager' => [
        'Property operations & maintenance oversight',
        'Tenant relations & lease management',
        'Budgeting & financial management',
        'Vendor & contractor coordination',
        'Regulatory compliance & reporting'
    ],
    'Estate Manager' => [
        'Property operations & maintenance oversight',
        'Tenant relations & lease management',
        'Budgeting & financial management',
        'Vendor & contractor coordination',
        'Regulatory compliance & reporting'
    ],
    'Leasing Officer' => [
        'Tenant acquisition & lease administration',
        'Property showing & client consultation',
        'Contract preparation & compliance',
        'Communication & negotiation skills',
        'Record keeping & reporting'
    ],
    'Property Leasing Specialist' => [
        'Tenant acquisition & lease administration',
        'Property showing & client consultation',
        'Contract preparation & compliance',
        'Communication & negotiation skills',
        'Record keeping & reporting'
    ],
    'Rental Officer' => [
        'Tenant acquisition & lease administration',
        'Property showing & client consultation',
        'Contract preparation & compliance',
        'Communication & negotiation skills',
        'Record keeping & reporting'
    ],
    'Appraiser' => [
        'Property valuation & market analysis',
        'Data collection & research',
        'Knowledge of real estate regulations & standards',
        'Analytical & reporting skills',
        'Attention to detail & accuracy'
    ],
    'Valuation Officer' => [
        'Property valuation & market analysis',
        'Data collection & research',
        'Knowledge of real estate regulations & standards',
        'Analytical & reporting skills',
        'Attention to detail & accuracy'
    ],
    'Real Estate Consultant' => [
        'Market research & feasibility analysis',
        'Client advisory & strategic planning',
        'Project management & coordination',
        'Financial analysis & investment evaluation',
        'Communication & presentation skills'
    ],
    'Development Manager' => [
        'Market research & feasibility analysis',
        'Client advisory & strategic planning',
        'Project management & coordination',
        'Financial analysis & investment evaluation',
        'Communication & presentation skills'
    ],
    
    // Entrepreneurship / Business / Corporate
    'Chief Executive Officer' => [
        'Strategic planning & vision setting',
        'Leadership & team management',
        'Decision-making & problem-solving',
        'Financial oversight & resource allocation',
        'Communication & stakeholder management'
    ],
    'Executive Director' => [
        'Strategic planning & vision setting',
        'Leadership & team management',
        'Decision-making & problem-solving',
        'Financial oversight & resource allocation',
        'Communication & stakeholder management'
    ],
    'Startup Founder' => [
        'Business planning & strategy development',
        'Innovation & opportunity recognition',
        'Fundraising & investor relations',
        'Team leadership & resource management',
        'Adaptability & problem-solving'
    ],
    'Entrepreneur' => [
        'Business planning & strategy development',
        'Innovation & opportunity recognition',
        'Fundraising & investor relations',
        'Team leadership & resource management',
        'Adaptability & problem-solving'
    ],
    'Business Analyst' => [
        'Data collection & business process analysis',
        'Problem-solving & decision support',
        'Reporting & documentation',
        'Process improvement & optimization',
        'Communication & stakeholder engagement'
    ],
    'Operations Analyst' => [
        'Data collection & business process analysis',
        'Problem-solving & decision support',
        'Reporting & documentation',
        'Process improvement & optimization',
        'Communication & stakeholder engagement'
    ],
    'Operations Manager' => [
        'Workflow & operational process management',
        'Staff supervision & coordination',
        'Resource allocation & scheduling',
        'Quality assurance & efficiency monitoring',
        'Problem-solving & reporting'
    ],
    'Project Manager' => [
        'Project planning & execution',
        'Resource & budget management',
        'Risk assessment & mitigation',
        'Team coordination & communication',
        'Monitoring & reporting project progress'
    ],
    'Management Consultant' => [
        'Business process evaluation & optimization',
        'Strategic planning & advisory',
        'Data analysis & problem-solving',
        'Communication & client relationship management',
        'Presentation & reporting'
    ],
    'Strategic Planner' => [
        'Long-term business strategy development',
        'Market research & analysis',
        'Goal setting & performance metrics',
        'Stakeholder engagement & communication',
        'Problem-solving & decision-making'
    ],
    'Corporate Officer' => [
        'Business growth strategy & planning',
        'Client relationship management',
        'Negotiation & deal-making',
        'Financial analysis & reporting',
        'Team leadership & coordination'
    ],
    'Business Development Manager' => [
        'Business growth strategy & planning',
        'Client relationship management',
        'Negotiation & deal-making',
        'Financial analysis & reporting',
        'Team leadership & coordination'
    ]
];

// Job title to qualification mapping
$jobTitleToQualifications = [
    'Office Administrator' => "Bachelor's degree in Business Administration or related field\n\nExperience in office operations and administration\n\nStrong organizational and multitasking skills\n\nProficient in MS Office (Word, Excel, Outlook)",
    'Executive Assistant' => "Bachelor's degree in Business, Management, or related field\n\nProven experience supporting senior executives\n\nExcellent communication and confidentiality skills\n\nStrong scheduling and coordination abilities",
    'Administrative Coordinator' => "Bachelor's degree in Business Administration or related field\n\nExperience in coordinating office activities\n\nStrong planning and reporting skills\n\nGood written and verbal communication",
    'Data Entry Clerk' => "At least High School / Senior High School graduate\n\nFast and accurate typing skills\n\nBasic computer knowledge (MS Excel, Word)\n\nAttention to detail and accuracy",
    'Office Manager' => "Bachelor's degree in Business Administration or Management\n\nLeadership and supervisory experience\n\nKnowledge of office budgeting and operations\n\nStrong decision-making and organizational skills",
    'Receptionist' => "At least Senior High School graduate\n\nGood communication and customer service skills\n\nBasic computer and telephone handling skills\n\nPresentable and professional demeanor",
    'Personal Assistant' => "Bachelor's degree or relevant experience\n\nStrong organizational and time-management skills\n\nAbility to handle confidential matters\n\nFlexible and willing to multitask",
    'Administrative Officer' => "Bachelor's degree in Business Administration or related field\n\nExperience in office administration and documentation\n\nStrong coordination and reporting skills\n\nKnowledge of office policies and procedures",
    'Records Clerk' => "At least Senior High School graduate\n\nExperience in filing and record-keeping\n\nAttention to detail and organizational skills\n\nBasic computer knowledge",
    'Operations Assistant' => "Bachelor's degree in Business, Operations, or related field\n\nExperience in administrative or operations support\n\nStrong coordination and problem-solving skills\n\nAbility to work under pressure",
    'Secretary' => "Bachelor's degree or secretarial course graduate\n\nExcellent written and verbal communication skills\n\nKnowledge in office procedures and correspondence\n\nProficient in MS Office",
    'Front Desk Officer' => "At least Senior High School graduate\n\nCustomer service–oriented with good communication skills\n\nAbility to handle visitors and phone inquiries\n\nProfessional appearance and attitude",
    'Executive Secretary' => "Bachelor's degree in Business or Secretarial Studies\n\nExperience supporting top-level management\n\nExcellent organizational and communication skills\n\nHigh level of confidentiality and professionalism",
    'Office Clerk' => "At least High School / Senior High School graduate\n\nBasic office and clerical knowledge\n\nAbility to follow instructions accurately\n\nGood organizational skills",
    'Filing Clerk' => "At least Senior High School graduate\n\nKnowledge of filing systems and document control\n\nAttention to detail and accuracy\n\nBasic computer skills",
    'Scheduling Coordinator' => "Bachelor's degree or relevant administrative experience\n\nStrong scheduling and time-management skills\n\nAbility to coordinate multiple calendars\n\nGood communication and organizational skills",
    'Office Services Manager' => "Bachelor's degree in Business Administration or Management\n\nExperience managing office services and facilities\n\nLeadership and vendor coordination skills\n\nKnowledge of office operations and budgeting",
    'Documentation Specialist' => "Bachelor's degree or relevant experience\n\nStrong documentation and reporting skills\n\nAttention to detail and accuracy\n\nKnowledge of document control systems",
    'Office Support Specialist' => "At least Senior High School graduate or Bachelor's degree\n\nExperience in general office support tasks\n\nStrong organizational and multitasking skills\n\nGood communication skills",
    'Office Supervisor' => "Bachelor's degree in Business Administration or Management\n\nSupervisory or team leadership experience\n\nStrong organizational and decision-making skills\n\nAbility to manage office staff and workflow",
    // Customer Service / BPO Qualifications
    'Customer Service Representative' => "At least Senior High School graduate (College preferred)\n\nGood verbal and written communication skills (English)\n\nBasic computer and typing skills\n\nCustomer-oriented and problem-solving skills",
    'Call Center Agent' => "At least Senior High School graduate\n\nClear English communication skills\n\nAbility to handle inbound/outbound calls\n\nFamiliarity with CRM systems or call software",
    'Client Support Specialist' => "Bachelor's degree or equivalent experience\n\nStrong customer service and relationship management skills\n\nKnowledge of company products/services\n\nGood problem-solving and multitasking abilities",
    'Help Desk Associate' => "Bachelor's degree or technical background preferred\n\nBasic IT knowledge and troubleshooting skills\n\nStrong communication and customer support skills\n\nAbility to document issues accurately",
    'Customer Care Coordinator' => "Bachelor's degree or equivalent experience\n\nStrong communication and coordination skills\n\nAbility to manage customer queries and escalations\n\nProficient in office and CRM software",
    'Technical Support Representative' => "Bachelor's degree in IT, Computer Science, or related field (preferred)\n\nKnowledge of software/hardware troubleshooting\n\nGood communication and problem-solving skills\n\nExperience in technical support is a plus",
    'Service Desk Analyst' => "Bachelor's degree in IT or relevant field\n\nKnowledge of ITIL or support ticketing systems\n\nStrong analytical and troubleshooting skills\n\nExcellent verbal and written communication",
    'Account Support Specialist' => "Bachelor's degree or equivalent experience\n\nAbility to manage client accounts and inquiries\n\nStrong communication and coordination skills\n\nAttention to detail and problem-solving",
    'Call Center Supervisor' => "Bachelor's degree preferred\n\nPrevious experience in call center or customer service\n\nLeadership and team management skills\n\nAbility to monitor KPIs and coach staff",
    'Customer Experience Associate' => "Bachelor's degree or equivalent experience\n\nStrong customer service and interpersonal skills\n\nKnowledge of CRM systems\n\nAbility to improve customer satisfaction and retention",
    'Contact Center Trainer' => "Bachelor's degree or training certification\n\nStrong communication and presentation skills\n\nExperience in call center operations\n\nAbility to develop training materials and programs",
    'Chat Support Agent' => "At least Senior High School graduate (College preferred)\n\nExcellent written communication skills\n\nAbility to multitask in chat-based support\n\nBasic computer and typing skills",
    'Email Support Specialist' => "Bachelor's degree or equivalent experience\n\nStrong written communication and email etiquette\n\nKnowledge of customer service processes\n\nAbility to manage multiple inquiries simultaneously",
    'Escalation Officer' => "Bachelor's degree preferred\n\nExperience handling escalated customer issues\n\nStrong problem-solving and negotiation skills\n\nAbility to coordinate with multiple departments",
    'QA Analyst (Customer Service)' => "Bachelor's degree or equivalent experience\n\nKnowledge of quality assurance procedures\n\nAttention to detail and analytical skills\n\nExperience in call monitoring or performance evaluation",
    'Customer Retention Specialist' => "Bachelor's degree or equivalent experience\n\nStrong negotiation and interpersonal skills\n\nKnowledge of customer retention strategies\n\nAbility to analyze customer feedback and trends",
    'Virtual Customer Service Associate' => "Bachelor's degree or relevant experience\n\nReliable internet connection and basic tech setup\n\nStrong communication and customer support skills\n\nAbility to work independently and manage time",
    'Inside Sales / Customer Support' => "Bachelor's degree or equivalent experience\n\nStrong sales and customer service skills\n\nGood communication and negotiation abilities\n\nFamiliarity with CRM and sales tools",
    'Team Lead – Customer Support' => "Bachelor's degree preferred\n\nProven experience leading a customer service team\n\nAbility to monitor performance, coach, and motivate staff\n\nStrong problem-solving and decision-making skills",
    // Education Qualifications
    'Teacher' => "Bachelor's degree in Education or relevant field\n\nTeaching license / PRC certification (if required)\n\nStrong classroom management and communication skills\n\nAbility to develop lesson plans and assess students",
    'School Counselor' => "Bachelor's degree in Psychology, Counseling, or Education\n\nRelevant certification or training in counseling\n\nStrong interpersonal and communication skills\n\nAbility to provide guidance and support to students",
    'Academic Coordinator' => "Bachelor's degree in Education or related field\n\nExperience in curriculum planning and coordination\n\nStrong organizational and communication skills\n\nAbility to monitor academic progress and performance",
    'Tutor' => "Bachelor's degree or subject-matter expertise\n\nGood teaching and mentoring skills\n\nAbility to adapt teaching methods to student needs\n\nPatient, communicative, and approachable",
    'Principal' => "Bachelor's degree in Education (Master's preferred)\n\nAdministrative experience in school leadership\n\nStrong leadership, organizational, and decision-making skills\n\nKnowledge of education laws, policies, and standards",
    'Librarian' => "Bachelor's degree in Library Science or Education\n\nKnowledge of cataloging, database, and library systems\n\nStrong organizational and information management skills\n\nAbility to assist students and staff with resources",
    'Special Education Teacher' => "Bachelor's degree in Special Education\n\nPRC license or teaching certification (if required)\n\nExperience with students with special needs\n\nStrong patience, communication, and individualized teaching skills",
    'Curriculum Developer' => "Bachelor's degree in Education, Instructional Design, or related field\n\nExperience in curriculum planning and educational content creation\n\nStrong research, analytical, and writing skills\n\nAbility to align curriculum with learning standards",
    'Education Program Manager' => "Bachelor's degree in Education or related field\n\nExperience in program development and management\n\nStrong organizational, leadership, and reporting skills\n\nAbility to oversee multiple projects and stakeholders",
    'Lecturer' => "Bachelor's degree in relevant field (Master's preferred)\n\nTeaching experience in higher education\n\nStrong subject knowledge and presentation skills\n\nAbility to develop and deliver lectures effectively",
    'College Instructor' => "Bachelor's degree in relevant field (Master's preferred)\n\nPRC license or teaching certification (if applicable)\n\nExperience in teaching college-level courses\n\nStrong research and instructional skills",
    'Preschool Teacher' => "Bachelor's degree in Early Childhood Education\n\nExperience with preschool-aged children\n\nKnowledge of child development and learning activities\n\nPatient, nurturing, and creative in teaching",
    'Teaching Assistant' => "Bachelor's degree in Education or related field\n\nExperience assisting in classroom instruction\n\nStrong organizational and communication skills\n\nAbility to support teacher and students effectively",
    'Instructional Designer' => "Bachelor's degree in Education, Instructional Design, or related field\n\nExperience in creating educational content and e-learning modules\n\nStrong analytical and design skills\n\nFamiliarity with learning management systems (LMS)",
    'Learning Facilitator' => "Bachelor's degree in Education or Training\n\nExperience facilitating workshops or learning programs\n\nStrong presentation and interpersonal skills\n\nAbility to engage learners and promote active participation",
    'Education Consultant' => "Bachelor's degree in Education or related field\n\nExperience in advisory or consulting roles in education\n\nStrong analytical, communication, and problem-solving skills\n\nKnowledge of curriculum, policies, and educational best practices",
    'Homeroom Teacher' => "Bachelor's degree in Education\n\nPRC license (if applicable)\n\nStrong classroom management and teaching skills\n\nAbility to monitor and support students' overall development",
    'School Administrator' => "Bachelor's degree in Education, Management, or related field\n\nExperience in administrative roles in schools\n\nStrong leadership, organizational, and communication skills\n\nKnowledge of school policies, budgeting, and reporting",
    'Guidance Counselor' => "Bachelor's degree in Psychology, Guidance, or Counseling\n\nPRC license or certification (if required)\n\nAbility to provide academic, career, and personal guidance\n\nStrong listening, empathy, and problem-solving skills",
    'Academic Adviser' => "Bachelor's degree in Education or related field\n\nKnowledge of academic policies and program requirements\n\nStrong advising, mentoring, and communication skills\n\nAbility to guide students in course selection and academic planning",
    // Engineering Qualifications
    'Civil Engineer' => "Bachelor's degree in Civil Engineering\n\nLicensed Professional Engineer (PE) preferred\n\nKnowledge in construction, design, and project management\n\nStrong problem-solving and analytical skills",
    'Mechanical Engineer' => "Bachelor's degree in Mechanical Engineering\n\nPE license preferred\n\nKnowledge in machinery, thermodynamics, and design\n\nExperience in manufacturing, maintenance, or project work",
    'Electrical Engineer' => "Bachelor's degree in Electrical Engineering\n\nLicensed PE preferred\n\nKnowledge in circuits, electrical systems, and safety regulations\n\nExperience in design, installation, or maintenance of electrical systems",
    'Project Engineer' => "Bachelor's degree in Engineering (any relevant field)\n\nExperience in project planning, execution, and supervision\n\nStrong analytical, organizational, and communication skills\n\nAbility to coordinate multidisciplinary teams",
    'Structural Engineer' => "Bachelor's degree in Civil or Structural Engineering\n\nPE license preferred\n\nExperience in designing and analyzing structural systems\n\nKnowledge of building codes and safety standards",
    'Chemical Engineer' => "Bachelor's degree in Chemical Engineering\n\nKnowledge in chemical processes, safety, and production\n\nExperience in process design or plant operations\n\nStrong analytical and problem-solving skills",
    'Industrial Engineer' => "Bachelor's degree in Industrial Engineering\n\nKnowledge in process improvement, productivity, and operations\n\nExperience in workflow optimization and cost reduction\n\nStrong analytical and project management skills",
    'Process Engineer' => "Bachelor's degree in Chemical, Mechanical, or Industrial Engineering\n\nExperience in process design, optimization, and troubleshooting\n\nKnowledge of production systems and quality standards\n\nStrong analytical and problem-solving skills",
    'Quality Engineer' => "Bachelor's degree in Engineering (any discipline)\n\nKnowledge of quality assurance systems and standards (ISO, Six Sigma)\n\nExperience in inspection, testing, and process improvement\n\nStrong attention to detail and analytical skills",
    'Design Engineer' => "Bachelor's degree in Mechanical, Electrical, Civil, or relevant Engineering\n\nExperience in CAD software and technical design\n\nStrong analytical, problem-solving, and creative skills\n\nAbility to develop and improve products or systems",
    'Maintenance Engineer' => "Bachelor's degree in Mechanical, Electrical, or Industrial Engineering\n\nExperience in equipment maintenance and troubleshooting\n\nKnowledge of preventive and predictive maintenance techniques\n\nStrong problem-solving and technical skills",
    'Field Engineer' => "Bachelor's degree in Engineering (Civil, Mechanical, Electrical, etc.)\n\nExperience in on-site project management and supervision\n\nStrong problem-solving and technical skills\n\nAbility to communicate and coordinate with teams on-site",
    'Systems Engineer' => "Bachelor's degree in Systems, Electrical, Mechanical, or Computer Engineering\n\nExperience in system design, integration, and troubleshooting\n\nKnowledge of engineering processes and standards\n\nStrong analytical and technical problem-solving skills",
    'Engineering Technician' => "Diploma or Bachelor's degree in Engineering Technology or related field\n\nExperience assisting engineers in design, testing, and operations\n\nKnowledge of technical drawings and specifications\n\nStrong technical and organizational skills",
    'Automation Engineer' => "Bachelor's degree in Electrical, Mechanical, or Mechatronics Engineering\n\nExperience in automation systems, PLC programming, or robotics\n\nStrong analytical, problem-solving, and technical skills\n\nAbility to design, implement, and maintain automated systems",
    'Product Design Engineer' => "Bachelor's degree in Mechanical, Industrial, or Product Design Engineering\n\nExperience in product development, prototyping, and CAD software\n\nStrong creativity, problem-solving, and technical knowledge\n\nAbility to collaborate with manufacturing and marketing teams",
    'Control Systems Engineer' => "Bachelor's degree in Electrical, Electronics, or Control Engineering\n\nExperience in control system design, PLC, and SCADA\n\nStrong analytical and troubleshooting skills\n\nKnowledge of safety standards and industrial automation",
    'Environmental Engineer' => "Bachelor's degree in Environmental, Civil, or Chemical Engineering\n\nKnowledge of environmental laws, regulations, and impact assessment\n\nExperience in environmental projects, waste management, or sustainability\n\nStrong analytical and problem-solving skills",
    'Safety Engineer' => "Bachelor's degree in Engineering or Occupational Safety\n\nKnowledge of safety regulations, risk assessment, and hazard management\n\nExperience in implementing safety programs and compliance\n\nStrong problem-solving and communication skills",
    'Reliability Engineer' => "Bachelor's degree in Engineering (Mechanical, Electrical, Industrial)\n\nExperience in reliability analysis, maintenance strategies, and failure analysis\n\nStrong problem-solving and data analysis skills\n\nKnowledge of reliability standards and methodologies",
    // Information Technology (IT) Qualifications
    'Software Developer' => "Bachelor's degree in Computer Science, IT, or related field\n\nProficient in programming languages (Java, Python, C#, etc.)\n\nExperience in software development lifecycle\n\nProblem-solving and analytical skills",
    'Network Administrator' => "Bachelor's degree in IT, Computer Engineering, or related field\n\nKnowledge of network protocols, LAN/WAN, and security\n\nExperience in configuring and managing network devices\n\nStrong troubleshooting and communication skills",
    'IT Support Specialist' => "Bachelor's degree in IT, Computer Science, or relevant field\n\nExperience in technical support and troubleshooting\n\nKnowledge of operating systems, software, and hardware\n\nStrong communication and problem-solving skills",
    'Web Developer' => "Bachelor's degree in IT, Computer Science, or related field\n\nProficient in HTML, CSS, JavaScript, and frameworks\n\nExperience with front-end and/or back-end development\n\nAbility to design, maintain, and optimize websites",
    'Systems Analyst' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience analyzing system requirements and workflows\n\nKnowledge of database, software, and IT infrastructure\n\nStrong analytical and communication skills",
    'Database Administrator' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience with SQL, Oracle, or other DBMS\n\nKnowledge of database security, backup, and recovery\n\nStrong problem-solving and analytical skills",
    'Cybersecurity Analyst' => "Bachelor's degree in IT, Cybersecurity, or related field\n\nKnowledge of network security, firewalls, and intrusion detection\n\nExperience in threat assessment and vulnerability management\n\nStrong analytical and problem-solving skills",
    'Cloud Engineer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience with AWS, Azure, or Google Cloud platforms\n\nKnowledge of cloud architecture and deployment\n\nStrong analytical, programming, and problem-solving skills",
    'IT Manager' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in IT operations, team management, and project management\n\nKnowledge of IT systems, security, and infrastructure\n\nStrong leadership and problem-solving skills",
    'Technical Lead' => "Bachelor's degree in IT or Computer Science\n\nExperience in software development or IT projects\n\nStrong leadership and mentoring skills\n\nProficient in programming, design, and problem-solving",
    'Application Developer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in developing mobile or desktop applications\n\nKnowledge of programming languages and frameworks\n\nStrong problem-solving and debugging skills",
    'DevOps Engineer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience with CI/CD pipelines, automation, and cloud platforms\n\nKnowledge of system administration, coding, and deployment\n\nStrong collaboration and problem-solving skills",
    'Mobile App Developer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in Android, iOS, or cross-platform development\n\nKnowledge of programming languages and mobile frameworks\n\nStrong design, coding, and problem-solving skills",
    'Data Engineer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience with ETL processes, databases, and data pipelines\n\nKnowledge of SQL, Python, or big data tools\n\nStrong analytical and problem-solving skills",
    'Network Security Engineer' => "Bachelor's degree in IT, Cybersecurity, or Computer Engineering\n\nKnowledge of firewalls, VPNs, IDS/IPS, and network security\n\nExperience in security monitoring and risk assessment\n\nStrong problem-solving and analytical skills",
    'IT Project Manager' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in managing IT projects and teams\n\nKnowledge of project management tools and methodologies\n\nStrong leadership, communication, and organizational skills",
    'UX/UI Developer' => "Bachelor's degree in IT, Design, or related field\n\nExperience in user interface and user experience design\n\nProficient in design tools (Figma, Adobe XD, etc.)\n\nStrong creativity and problem-solving skills",
    'Front-End Developer' => "Bachelor's degree in IT, Computer Science, or related field\n\nProficient in HTML, CSS, JavaScript, and frameworks\n\nExperience in responsive web design and performance optimization\n\nStrong problem-solving and communication skills",
    'Back-End Developer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in server-side programming (Node.js, PHP, Python, etc.)\n\nKnowledge of databases, APIs, and web services\n\nStrong problem-solving and analytical skills",
    'IT Infrastructure Engineer' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in managing servers, storage, and network infrastructure\n\nKnowledge of virtualization, cloud, and security\n\nStrong analytical and troubleshooting skills",
    'IT Consultant' => "Bachelor's degree in IT, Computer Science, or related field\n\nExperience in IT strategy, advisory, and project implementation\n\nStrong communication, analytical, and problem-solving skills\n\nKnowledge of technology trends and best practices",
    'IT Auditor' => "Bachelor's degree in IT, Accounting, or related field\n\nKnowledge of IT controls, audits, and compliance standards\n\nExperience in evaluating systems, security, and risk\n\nStrong analytical and reporting skills",
    // Finance / Accounting Qualifications
    'Accountant' => "Bachelor's degree in Accounting or Finance\n\nCPA license preferred\n\nKnowledge of bookkeeping, accounting standards, and financial reporting\n\nStrong analytical and problem-solving skills",
    'Financial Analyst' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in financial modeling, budgeting, and forecasting\n\nStrong analytical and Excel skills\n\nAbility to interpret financial data for decision-making",
    'Bookkeeper' => "Bachelor's degree in Accounting, Finance, or related field (or diploma)\n\nKnowledge of bookkeeping, ledgers, and accounting software\n\nStrong attention to detail and organizational skills\n\nAbility to manage financial records accurately",
    'Payroll Officer' => "Bachelor's degree in Accounting, Finance, or HR-related field\n\nExperience in payroll processing and compliance\n\nKnowledge of payroll software and tax regulations\n\nStrong numerical and organizational skills",
    'Tax Specialist' => "Bachelor's degree in Accounting, Finance, or Taxation\n\nKnowledge of tax laws, regulations, and filing requirements\n\nCPA license preferred\n\nStrong analytical, research, and reporting skills",
    'Budget Analyst' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in budgeting, forecasting, and financial planning\n\nStrong analytical and Excel skills\n\nAbility to prepare and monitor budgets efficiently",
    'Auditor' => "Bachelor's degree in Accounting or Finance\n\nCPA license preferred\n\nExperience in internal or external auditing\n\nKnowledge of accounting standards and compliance\n\nStrong analytical and investigative skills",
    'Finance Manager' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in financial management and reporting\n\nStrong leadership, analytical, and decision-making skills\n\nKnowledge of budgeting, forecasting, and financial analysis",
    'Credit Analyst' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in evaluating credit risk and financial statements\n\nStrong analytical and decision-making skills\n\nKnowledge of lending regulations and risk management",
    'Controller' => "Bachelor's degree in Accounting, Finance, or related field\n\nCPA license preferred\n\nExperience in overseeing accounting operations and financial reporting\n\nStrong leadership, analytical, and organizational skills",
    'Cost Accountant' => "Bachelor's degree in Accounting, Finance, or related field\n\nExperience in cost analysis, budgeting, and financial planning\n\nKnowledge of cost accounting methods and standards\n\nStrong analytical and problem-solving skills",
    'Treasury Analyst' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in cash management, investments, and financial planning\n\nKnowledge of treasury operations and banking\n\nStrong analytical and reporting skills",
    'Accounts Payable Clerk' => "Bachelor's degree in Accounting, Finance, or related field\n\nExperience in processing invoices, payments, and reconciliations\n\nKnowledge of accounting software and internal controls\n\nStrong attention to detail and organizational skills",
    'Accounts Receivable Clerk' => "Bachelor's degree in Accounting, Finance, or related field\n\nExperience in billing, collections, and reconciliations\n\nKnowledge of accounting software and financial reporting\n\nStrong communication and organizational skills",
    'Finance Officer' => "Bachelor's degree in Accounting, Finance, or Economics\n\nExperience in financial operations, reporting, and compliance\n\nStrong analytical and decision-making skills\n\nKnowledge of accounting standards and financial systems",
    'Investment Analyst' => "Bachelor's degree in Finance, Economics, or Accounting\n\nExperience in investment analysis, portfolio management, and valuation\n\nStrong analytical, research, and financial modeling skills\n\nKnowledge of financial markets and instruments",
    'Risk Officer' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in risk assessment, compliance, and reporting\n\nStrong analytical and problem-solving skills\n\nKnowledge of risk management frameworks and regulations",
    'Compliance Officer – Finance' => "Bachelor's degree in Finance, Accounting, or Law\n\nKnowledge of financial regulations and compliance standards\n\nExperience in auditing, risk management, and reporting\n\nStrong analytical, ethical, and problem-solving skills",
    'Loan Officer' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in evaluating loan applications and credit risk\n\nKnowledge of lending policies and regulations\n\nStrong communication, analytical, and decision-making skills",
    'Fund Accountant' => "Bachelor's degree in Accounting, Finance, or Economics\n\nExperience in investment fund accounting and reporting\n\nKnowledge of accounting standards and financial instruments\n\nStrong analytical and numerical skills",
    'Billing Officer' => "Bachelor's degree in Accounting, Finance, or related field\n\nExperience in invoicing, billing, and reconciliations\n\nKnowledge of accounting software and internal controls\n\nStrong organizational and numerical skills",
    'Treasury Officer' => "Bachelor's degree in Finance, Accounting, or Economics\n\nExperience in cash management, liquidity, and banking operations\n\nKnowledge of treasury policies and financial reporting\n\nStrong analytical and problem-solving skills",
    // Healthcare / Medical Qualifications
    'Doctor' => "Doctor of Medicine (MD) degree\n\nLicensed to practice medicine in the Philippines (PRC license)\n\nSpecialization (optional) for specific fields like internal medicine, pediatrics, surgery, etc.\n\nStrong diagnostic, clinical, and patient management skills\n\nGood communication and ethical decision-making skills",
    'Physician' => "Doctor of Medicine (MD) degree\n\nLicensed to practice medicine in the Philippines (PRC license)\n\nSpecialization (optional) for specific fields like internal medicine, pediatrics, surgery, etc.\n\nStrong diagnostic, clinical, and patient management skills\n\nGood communication and ethical decision-making skills",
    'Nurse' => "Bachelor of Science in Nursing (BSN)\n\nLicensed Nurse (PRC)\n\nKnowledge of patient care, medical procedures, and hospital protocols\n\nStrong empathy, communication, and critical thinking skills",
    'Medical Technologist' => "Bachelor's degree in Medical Technology / Clinical Laboratory Science\n\nLicensed Medical Technologist (PRC)\n\nKnowledge of laboratory procedures, tests, and equipment\n\nStrong attention to detail and analytical skills",
    'Pharmacist' => "Bachelor of Science in Pharmacy\n\nLicensed Pharmacist (PRC)\n\nKnowledge of medications, dosages, and drug interactions\n\nStrong attention to detail and counseling skills",
    'Dentist' => "Doctor of Dental Medicine (DMD) or Doctor of Dental Surgery (DDS)\n\nLicensed Dentist (PRC)\n\nKnowledge of dental procedures, patient care, and oral health\n\nGood manual dexterity and communication skills",
    'Radiologic Technologist' => "Bachelor's degree in Radiologic Technology\n\nLicensed Radiologic Technologist (PRC)\n\nKnowledge of imaging procedures, equipment, and safety standards\n\nAttention to detail and patient care skills",
    'Physical Therapist' => "Bachelor's or Doctoral degree in Physical Therapy\n\nLicensed Physical Therapist (PRC)\n\nKnowledge of rehabilitation techniques and patient assessment\n\nStrong interpersonal and motivational skills",
    'Occupational Therapist' => "Bachelor's or Master's degree in Occupational Therapy\n\nLicensed Occupational Therapist (PRC)\n\nKnowledge of rehabilitation, therapy planning, and adaptive techniques\n\nStrong problem-solving and communication skills",
    'Laboratory Technician' => "Diploma or Bachelor's degree in Medical Laboratory Technology or related field\n\nKnowledge of lab procedures and sample testing\n\nAbility to maintain equipment and records\n\nAttention to detail and safety awareness",
    'Midwife' => "Bachelor's degree in Midwifery\n\nLicensed Midwife (PRC)\n\nKnowledge of maternal and neonatal care\n\nStrong interpersonal and emergency response skills",
    'Paramedic' => "Bachelor's degree in Paramedicine / EMT certification\n\nKnowledge of emergency care, life support, and patient transport\n\nStrong decision-making and quick-response skills",
    'Dietitian' => "Bachelor's degree in Nutrition and Dietetics\n\nLicensed Nutritionist-Dietitian (PRC)\n\nKnowledge of diet planning, health assessments, and nutrition therapy\n\nStrong analytical and counseling skills",
    'Nurse Practitioner' => "Bachelor's degree in Nursing (BSN) + advanced practice certification\n\nLicensed Nurse (PRC)\n\nAdvanced knowledge in patient care and treatment planning\n\nStrong clinical judgment and communication skills",
    'Anesthesiologist' => "Doctor of Medicine (MD) + specialization in Anesthesiology\n\nLicensed Physician (PRC)\n\nKnowledge of anesthesia techniques, patient monitoring, and perioperative care\n\nHigh attention to detail and stress management skills",
    'Surgeon' => "Doctor of Medicine (MD) + surgical residency/specialization\n\nLicensed Physician (PRC)\n\nKnowledge of surgical procedures, pre/postoperative care\n\nExcellent manual dexterity, decision-making, and focus under pressure",
    'Medical Assistant' => "Diploma or Bachelor's degree in Medical Assisting or related field\n\nKnowledge of basic medical procedures, patient care, and administrative support\n\nStrong communication, organizational, and clinical skills",
    'Health Information Technician' => "Bachelor's degree in Health Information Management or related field\n\nKnowledge of medical coding, records management, and health IT systems\n\nAttention to detail and data management skills",
    'Speech Therapist' => "Bachelor's or Master's degree in Speech-Language Pathology\n\nLicensed Speech Therapist (PRC)\n\nKnowledge of communication disorders, therapy techniques, and patient assessment\n\nStrong interpersonal and counseling skills",
    'Psychologist' => "Bachelor's and Master's or Doctoral degree in Psychology\n\nLicensed Psychologist (PRC)\n\nKnowledge of mental health assessments, therapy techniques, and counseling\n\nStrong empathy, communication, and analytical skills",
    'Care Coordinator' => "Bachelor's degree in Nursing, Healthcare Administration, or related field\n\nExperience in patient management and healthcare coordination\n\nStrong organizational, communication, and problem-solving skills",
    'Emergency Medical Technician' => "EMT certification or relevant healthcare diploma\n\nKnowledge of emergency care, life support, and patient transport\n\nStrong decision-making and quick-response skills",
    'Clinical Coordinator' => "Bachelor's degree in Nursing, Healthcare, or Medical Technology\n\nExperience in coordinating clinical operations, staff, and patient care\n\nStrong leadership, communication, and organizational skills",
    // Human Resources (HR) Qualifications
    'HR Manager' => "Bachelor's degree in Human Resources, Business Administration, or related field\n\nSeveral years of HR experience in recruitment, employee relations, and HR policies\n\nStrong leadership, organizational, and communication skills\n\nKnowledge of labor laws and compliance regulations",
    'Recruitment Specialist' => "Bachelor's degree in Human Resources, Business, or related field\n\nExperience in talent acquisition and recruitment processes\n\nStrong interviewing, sourcing, and negotiation skills\n\nKnowledge of HR software and applicant tracking systems",
    'HR Generalist' => "Bachelor's degree in Human Resources, Business Administration, or related field\n\nExperience in multiple HR functions (recruitment, payroll, employee relations)\n\nStrong organizational and problem-solving skills\n\nKnowledge of labor laws and HR best practices",
    'Training Coordinator' => "Bachelor's degree in HR, Education, or related field\n\nExperience in planning, organizing, and delivering training programs\n\nStrong presentation and communication skills\n\nKnowledge of training tools and learning management systems",
    'Talent Acquisition Officer' => "Bachelor's degree in HR, Business, or related field\n\nExperience in sourcing, interviewing, and onboarding candidates\n\nKnowledge of recruitment strategies and employer branding\n\nStrong interpersonal and negotiation skills",
    'Compensation & Benefits Specialist' => "Bachelor's degree in HR, Business, or Finance\n\nExperience in payroll, benefits administration, and compensation management\n\nKnowledge of compensation laws and HRIS systems\n\nStrong analytical and organizational skills",
    'HR Assistant' => "Bachelor's degree in HR, Business, or related field\n\nBasic knowledge of HR processes, payroll, and recruitment\n\nStrong organizational and communication skills\n\nProficiency in MS Office and HR software",
    'Employee Relations Officer' => "Bachelor's degree in HR, Business, or related field\n\nExperience in handling employee relations, conflict resolution, and grievances\n\nKnowledge of labor laws and HR policies\n\nStrong interpersonal and problem-solving skills",
    'HR Business Partner' => "Bachelor's degree in HR, Business, or related field\n\nExperience in strategic HR management and business alignment\n\nStrong analytical, communication, and leadership skills\n\nKnowledge of organizational development and HR metrics",
    'Learning & Development Officer' => "Bachelor's degree in HR, Education, or related field\n\nExperience in training, employee development, and performance management\n\nKnowledge of learning management systems and instructional design\n\nStrong facilitation and communication skills",
    'HR Coordinator' => "Bachelor's degree in HR, Business, or related field\n\nExperience in coordinating HR activities, recruitment, and onboarding\n\nStrong organizational and communication skills\n\nKnowledge of HR software and labor laws",
    'Payroll Specialist' => "Bachelor's degree in Accounting, HR, or related field\n\nExperience in payroll processing, benefits, and compliance\n\nKnowledge of payroll software and labor regulations\n\nStrong numerical and analytical skills",
    'HR Analyst' => "Bachelor's degree in HR, Business, or related field\n\nExperience in HR reporting, data analysis, and metrics tracking\n\nStrong analytical and Excel skills\n\nKnowledge of HRIS systems and workforce planning",
    'Recruitment Coordinator' => "Bachelor's degree in HR, Business, or related field\n\nExperience in scheduling interviews, candidate communications, and onboarding\n\nStrong organizational and communication skills\n\nKnowledge of recruitment software",
    'HR Consultant' => "Bachelor's degree in HR, Business, or related field\n\nExperience in advising organizations on HR strategy, policies, and compliance\n\nStrong problem-solving and analytical skills\n\nKnowledge of labor laws and HR best practices",
    'Onboarding Specialist' => "Bachelor's degree in HR, Business, or related field\n\nExperience in employee onboarding, orientation, and documentation\n\nStrong communication and organizational skills\n\nKnowledge of HRIS and compliance requirements",
    'HR Officer' => "Bachelor's degree in HR, Business, or related field\n\nExperience in HR administration, recruitment, and employee relations\n\nKnowledge of labor laws and HR best practices\n\nStrong organizational and interpersonal skills",
    'HR Administrator' => "Bachelor's degree in HR, Business, or related field\n\nExperience in HR administrative tasks, record-keeping, and HRIS\n\nStrong organizational, detail-oriented, and communication skills\n\nKnowledge of HR policies and compliance",
    // Logistics / Warehouse / Supply Chain Qualifications
    'Warehouse Supervisor' => "Bachelor's degree in Logistics, Supply Chain, or related field\n\nExperience in supervising warehouse operations and teams\n\nKnowledge of inventory management, safety, and shipping procedures\n\nStrong leadership and organizational skills",
    'Logistics Coordinator' => "Bachelor's degree in Logistics, Supply Chain, or Business\n\nExperience coordinating shipments, deliveries, and inventory\n\nKnowledge of transportation and warehouse management systems\n\nStrong communication and problem-solving skills",
    'Inventory Clerk' => "High school diploma or Bachelor's in Logistics/Business\n\nExperience in stock control, record-keeping, and reporting\n\nAttention to detail and organizational skills\n\nFamiliarity with inventory management software",
    'Supply Chain Analyst' => "Bachelor's degree in Supply Chain, Business, or Engineering\n\nExperience analyzing supply chain operations and performance metrics\n\nKnowledge of ERP systems and data analysis tools\n\nStrong analytical, problem-solving, and communication skills",
    'Shipping & Receiving Clerk' => "High school diploma or Bachelor's in Logistics/Business\n\nExperience in shipping, receiving, and documentation\n\nKnowledge of packaging, labeling, and safety procedures\n\nOrganizational skills and attention to detail",
    'Transport Planner' => "Bachelor's degree in Logistics, Supply Chain, or Transport Management\n\nExperience in route planning, scheduling, and fleet management\n\nKnowledge of transport regulations and logistics software\n\nStrong analytical and problem-solving skills",
    'Procurement Officer' => "Bachelor's degree in Supply Chain, Business, or Finance\n\nExperience in sourcing, vendor management, and procurement processes\n\nKnowledge of contract negotiation and procurement software\n\nStrong analytical and negotiation skills",
    'Fleet Manager' => "Bachelor's degree in Logistics, Transport Management, or Business\n\nExperience managing vehicles, drivers, and maintenance schedules\n\nKnowledge of fleet management systems and transportation regulations\n\nStrong leadership, planning, and organizational skills",
    'Distribution Manager' => "Bachelor's degree in Supply Chain, Logistics, or Business\n\nExperience managing distribution centers, shipping, and delivery operations\n\nKnowledge of inventory and warehouse management systems\n\nStrong leadership, organizational, and problem-solving skills",
    'Order Fulfillment Officer' => "High school diploma or Bachelor's in Logistics/Business\n\nExperience in picking, packing, and processing orders\n\nKnowledge of warehouse operations and inventory systems\n\nAttention to detail and organizational skills",
    'Warehouse Staff' => "High school diploma or vocational certificate\n\nExperience in handling warehouse operations preferred\n\nKnowledge of inventory and material handling\n\nPhysical stamina and adherence to safety protocols",
    'Logistics Officer' => "Bachelor's degree in Logistics, Supply Chain, or Business\n\nExperience coordinating transportation, deliveries, and inventory\n\nKnowledge of shipping, procurement, and warehouse procedures\n\nStrong organizational and communication skills",
    'Stock Controller' => "Bachelor's degree in Supply Chain, Business, or related field\n\nExperience in inventory control, stock audits, and reporting\n\nKnowledge of ERP and inventory software\n\nAttention to detail and analytical skills",
    'Delivery Coordinator' => "High school diploma or Bachelor's in Logistics/Business\n\nExperience coordinating delivery schedules and drivers\n\nKnowledge of transport regulations and route planning\n\nStrong organizational and communication skills",
    'Supply Officer' => "Bachelor's degree in Logistics, Supply Chain, or related field\n\nExperience managing inventory, supplies, and procurement\n\nKnowledge of supply chain operations and warehouse management\n\nStrong analytical, organizational, and leadership skills",
    'Logistics Manager' => "Bachelor's degree in Logistics, Supply Chain, or Business\n\nExtensive experience in overseeing logistics, warehousing, and transportation operations\n\nStrong leadership, problem-solving, and strategic planning skills\n\nKnowledge of supply chain systems, safety, and compliance regulations",
    // Marketing / Sales Qualifications
    'Marketing Specialist' => "Bachelor's degree in Marketing, Business, or related field\n\nExperience in marketing campaigns, digital marketing, and content creation\n\nKnowledge of marketing analytics, SEO, and social media tools\n\nStrong communication and analytical skills",
    'Sales Executive' => "Bachelor's degree in Business, Marketing, or related field\n\nProven experience in sales and meeting targets\n\nStrong negotiation, communication, and interpersonal skills\n\nKnowledge of CRM software and sales processes",
    'Brand Manager' => "Bachelor's degree in Marketing, Business, or related field\n\nExperience managing brand strategy, campaigns, and positioning\n\nKnowledge of market research, digital marketing, and advertising\n\nStrong leadership and strategic thinking skills",
    'Account Manager' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience managing client accounts and relationships\n\nKnowledge of sales strategies, CRM systems, and customer service\n\nStrong communication, negotiation, and organizational skills",
    'Social Media Manager' => "Bachelor's degree in Marketing, Communications, or related field\n\nExperience managing social media platforms and campaigns\n\nKnowledge of analytics, social media tools, and content strategy\n\nStrong creative, communication, and organizational skills",
    'Marketing Coordinator' => "Bachelor's degree in Marketing, Business, or related field\n\nExperience coordinating marketing campaigns, events, and projects\n\nKnowledge of digital marketing and marketing analytics\n\nStrong organizational and communication skills",
    'Business Development Officer' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience in sales, client acquisition, and business growth\n\nStrong networking, negotiation, and strategic planning skills\n\nKnowledge of market research and competitor analysis",
    'Advertising Specialist' => "Bachelor's degree in Marketing, Advertising, or Communications\n\nExperience in advertising campaigns, media planning, and creative strategy\n\nKnowledge of digital marketing, SEO, and social media\n\nStrong analytical and communication skills",
    'Digital Marketing Analyst' => "Bachelor's degree in Marketing, IT, or related field\n\nExperience in digital marketing analytics, SEO, and PPC campaigns\n\nKnowledge of Google Analytics, AdWords, and social media tools\n\nStrong analytical, research, and reporting skills",
    'Product Manager' => "Bachelor's degree in Marketing, Business, or related field\n\nExperience managing product development, launch, and lifecycle\n\nKnowledge of market research, strategy, and customer analysis\n\nStrong leadership, analytical, and communication skills",
    'Sales Supervisor' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience supervising sales teams and achieving sales targets\n\nStrong leadership, coaching, and communication skills\n\nKnowledge of sales processes, CRM systems, and reporting",
    'Key Account Manager' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience managing key client accounts and building relationships\n\nStrong negotiation, strategic planning, and problem-solving skills\n\nKnowledge of CRM systems and sales strategy",
    'Territory Sales Manager' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience managing sales territories and achieving targets\n\nStrong leadership, planning, and interpersonal skills\n\nKnowledge of market trends, customer needs, and sales analytics",
    'Marketing Analyst' => "Bachelor's degree in Marketing, Business, or related field\n\nExperience in analyzing market data, trends, and campaigns\n\nKnowledge of analytics tools, market research, and reporting\n\nStrong analytical, problem-solving, and communication skills",
    'Event Marketing Coordinator' => "Bachelor's degree in Marketing, Communications, or related field\n\nExperience planning and executing events, promotions, and campaigns\n\nStrong organizational, communication, and problem-solving skills\n\nKnowledge of event management tools and marketing strategies",
    'Promotions Officer' => "Bachelor's degree in Marketing, Business, or related field\n\nExperience in promotions planning, campaigns, and sales support\n\nStrong communication, marketing, and organizational skills\n\nKnowledge of social media, branding, and customer engagement",
    // Construction / Infrastructure Qualifications
    'Construction Manager' => "Bachelor's degree in Civil Engineering, Construction Management, or Architecture\n\nExtensive experience managing construction projects from planning to completion\n\nStrong leadership, project management, and budgeting skills\n\nKnowledge of construction codes, safety regulations, and contracts",
    'Site Engineer' => "Bachelor's degree in Civil, Structural, or Construction Engineering\n\nExperience supervising on-site construction activities\n\nKnowledge of building codes, surveying, and construction methods\n\nStrong problem-solving and communication skills",
    'Architect' => "Bachelor's or Master's degree in Architecture\n\nLicensed architect (if required by local regulations)\n\nExperience designing building plans, layouts, and structures\n\nProficient in CAD, Revit, or other architectural design software\n\nStrong creativity and attention to detail",
    'Foreman' => "High school diploma or Bachelor's in Construction-related field\n\nExperience supervising construction crews and daily site operations\n\nKnowledge of safety procedures, tools, and construction techniques\n\nLeadership, communication, and organizational skills",
    'Project Manager' => "Bachelor's degree in Civil Engineering, Construction Management, or related field\n\nExperience managing construction projects, budgets, and schedules\n\nStrong leadership, risk management, and communication skills\n\nKnowledge of project management software and construction processes",
    'Quantity Surveyor' => "Bachelor's degree in Quantity Surveying, Civil Engineering, or related field\n\nExperience in cost estimation, contract administration, and budgeting\n\nKnowledge of construction costs, procurement, and tendering\n\nStrong analytical and negotiation skills",
    'Civil Technician' => "Bachelor's degree in Civil Engineering Technology or related field\n\nExperience assisting engineers in site surveys, drafting, and inspections\n\nKnowledge of construction materials, methods, and regulations\n\nStrong technical and analytical skills",
    'Structural Designer' => "Bachelor's degree in Civil or Structural Engineering\n\nExperience designing structural components of buildings or infrastructure\n\nProficient in AutoCAD, STAAD Pro, or similar structural software\n\nKnowledge of building codes, safety, and material properties",
    'Safety Officer' => "Bachelor's degree in Occupational Safety, Engineering, or related field\n\nKnowledge of construction site safety regulations and protocols\n\nExperience conducting safety audits and training\n\nStrong attention to detail and communication skills",
    'Building Inspector' => "Bachelor's degree in Civil Engineering, Architecture, or related field\n\nKnowledge of building codes, safety, and construction regulations\n\nExperience inspecting building structures and issuing compliance reports\n\nStrong analytical and reporting skills",
    'Construction Supervisor' => "Bachelor's degree in Civil Engineering, Construction Management, or related field\n\nExperience overseeing construction projects and crews\n\nKnowledge of construction methods, tools, and safety protocols\n\nLeadership, organizational, and problem-solving skills",
    'Field Engineer' => "Bachelor's degree in Civil, Structural, or Construction Engineering\n\nExperience monitoring construction sites, quality control, and project execution\n\nKnowledge of surveying, drafting, and construction materials\n\nStrong analytical, communication, and technical skills",
    'Project Engineer' => "Bachelor's degree in Civil, Mechanical, or Construction Engineering\n\nExperience in project planning, scheduling, and technical support\n\nKnowledge of construction methods, budgeting, and quality control\n\nStrong organizational, communication, and problem-solving skills",
    'Site Supervisor' => "Bachelor's degree in Civil Engineering, Construction Management, or related field\n\nExperience supervising site activities, crews, and materials\n\nKnowledge of construction processes, safety, and regulations\n\nLeadership, organizational, and decision-making skills",
    'Estimator' => "Bachelor's degree in Quantity Surveying, Civil Engineering, or related field\n\nExperience in cost estimation, bid preparation, and project budgeting\n\nKnowledge of materials, labor costs, and construction methods\n\nStrong analytical and attention-to-detail skills",
    // Food / Hospitality / Tourism Qualifications
    'Chef' => "Bachelor's degree in Culinary Arts or related field (preferred)\n\nExtensive experience in professional kitchens\n\nKnowledge of food safety, hygiene, and menu planning\n\nStrong leadership, creativity, and time management skills",
    'Sous Chef' => "Culinary degree or diploma\n\nExperience assisting head chefs in managing kitchen operations\n\nKnowledge of cooking techniques, inventory, and food safety\n\nStrong organizational, leadership, and teamwork skills",
    'Line Cook' => "Culinary diploma or equivalent experience\n\nProficient in food preparation, cooking techniques, and kitchen equipment\n\nKnowledge of food safety and hygiene standards\n\nAbility to work efficiently under pressure",
    'Prep Cook' => "Culinary diploma or on-the-job experience\n\nExperience preparing ingredients and supporting kitchen staff\n\nKnowledge of kitchen safety and sanitation\n\nStrong attention to detail and teamwork skills",
    'Grill Cook' => "Culinary diploma or experience in specific cooking stations\n\nKnowledge of grilling, frying, or breakfast menu preparation\n\nUnderstanding of food safety and hygiene\n\nAbility to work in fast-paced kitchen environments",
    'Fry Cook' => "Culinary diploma or experience in specific cooking stations\n\nKnowledge of grilling, frying, or breakfast menu preparation\n\nUnderstanding of food safety and hygiene\n\nAbility to work in fast-paced kitchen environments",
    'Breakfast Cook' => "Culinary diploma or experience in specific cooking stations\n\nKnowledge of grilling, frying, or breakfast menu preparation\n\nUnderstanding of food safety and hygiene\n\nAbility to work in fast-paced kitchen environments",
    'Pastry / Dessert Cook' => "Degree or diploma in Baking and Pastry Arts\n\nExperience creating pastries, desserts, and baked goods\n\nKnowledge of baking techniques, food safety, and ingredient handling\n\nCreativity and attention to detail",
    'Baker' => "Degree or diploma in Baking and Pastry Arts\n\nExperience creating pastries, desserts, and baked goods\n\nKnowledge of baking techniques, food safety, and ingredient handling\n\nCreativity and attention to detail",
    'Barista' => "Certificate in Barista Training or Food & Beverage\n\nExperience in preparing coffee, beverages, and customer service\n\nKnowledge of espresso machines, coffee beans, and brewing techniques\n\nStrong interpersonal and communication skills",
    'Crew Member' => "High school diploma or equivalent\n\nExperience in food handling, customer service, or hospitality (preferred)\n\nKnowledge of food safety, hygiene, and restaurant operations\n\nTeamwork, communication, and customer service skills",
    'Fast Food Crew' => "High school diploma or equivalent\n\nExperience in food handling, customer service, or hospitality (preferred)\n\nKnowledge of food safety, hygiene, and restaurant operations\n\nTeamwork, communication, and customer service skills",
    'Restaurant Manager' => "Bachelor's degree in Hospitality, Business, or related field\n\nExperience managing restaurant operations, staff, and budgets\n\nKnowledge of food service standards, customer service, and inventory\n\nLeadership, problem-solving, and organizational skills",
    'Kitchen Staff' => "High school diploma or vocational training\n\nExperience in food preparation and kitchen assistance\n\nKnowledge of hygiene, sanitation, and safety procedures\n\nTeamwork, reliability, and efficiency",
    'Shift Supervisor' => "Bachelor's degree in Hospitality or related field (preferred)\n\nExperience supervising restaurant or hotel staff\n\nKnowledge of operations, customer service, and safety standards\n\nLeadership, communication, and problem-solving skills",
    'Cashier' => "High school diploma or equivalent\n\nExperience handling cash, POS systems, and customer transactions\n\nStrong numeracy, attention to detail, and communication skills",
    'Host / Hostess' => "High school diploma or equivalent\n\nExperience in customer service, greeting, and seating guests\n\nStrong communication, interpersonal, and organizational skills",
    'Food Runner' => "High school diploma or vocational training\n\nExperience serving food and beverages in restaurants or hotels\n\nKnowledge of menu items and customer service etiquette\n\nStrong communication, teamwork, and multitasking skills",
    'Waiter / Waitress' => "High school diploma or vocational training\n\nExperience serving food and beverages in restaurants or hotels\n\nKnowledge of menu items and customer service etiquette\n\nStrong communication, teamwork, and multitasking skills",
    'Bartender' => "Certificate or diploma in Bartending or mixology (preferred)\n\nExperience in preparing drinks, cocktails, and customer service\n\nKnowledge of beverages, safety, and hygiene\n\nCreativity, interpersonal skills, and attention to detail",
    'Hotel Front Desk Officer' => "Bachelor's degree in Hospitality, Tourism, or related field (preferred)\n\nExperience in hotel front desk, reservations, and guest services\n\nKnowledge of booking systems, customer service, and problem-solving\n\nStrong communication, organization, and interpersonal skills",
    'Concierge' => "Bachelor's degree in Hospitality, Tourism, or related field (preferred)\n\nExperience in hotel front desk, reservations, and guest services\n\nKnowledge of booking systems, customer service, and problem-solving\n\nStrong communication, organization, and interpersonal skills",
    'Tour Guide' => "Bachelor's degree in Tourism, Hospitality, or related field (preferred)\n\nKnowledge of local culture, history, and tourist attractions\n\nStrong communication, storytelling, and customer service skills\n\nExperience leading groups and managing schedules",
    'Event Coordinator' => "Bachelor's degree in Hospitality, Event Management, or related field\n\nExperience planning and executing events or catering services\n\nStrong organizational, communication, and multitasking skills\n\nKnowledge of logistics, vendors, and customer service",
    'Catering Staff' => "Bachelor's degree in Hospitality, Event Management, or related field\n\nExperience planning and executing events or catering services\n\nStrong organizational, communication, and multitasking skills\n\nKnowledge of logistics, vendors, and customer service",
    // Retail / Sales Operations Qualifications
    'Store Manager' => "Bachelor's degree in Business, Management, or related field\n\nExperience managing retail operations, staff, and sales targets\n\nKnowledge of inventory management, merchandising, and customer service\n\nStrong leadership, problem-solving, and organizational skills",
    'Sales Associate' => "High school diploma or equivalent (Bachelor's preferred)\n\nExperience in retail sales, customer service, or product knowledge\n\nStrong communication, interpersonal, and customer service skills\n\nAbility to work in a fast-paced environment",
    'Merchandiser' => "High school diploma or Bachelor's in Marketing or Business (preferred)\n\nExperience in product display, stock management, and sales support\n\nKnowledge of merchandising strategies and inventory management\n\nAttention to detail, creativity, and organizational skills",
    'Cashier' => "High school diploma or equivalent\n\nExperience handling cash, POS systems, and customer transactions\n\nNumeracy, attention to detail, and communication skills\n\nReliability and honesty",
    'Retail Supervisor' => "Bachelor's degree in Business, Management, or related field\n\nExperience supervising retail staff and floor operations\n\nKnowledge of sales targets, inventory, and customer service\n\nLeadership, problem-solving, and organizational skills",
    'Stock Clerk' => "High school diploma or equivalent\n\nExperience in inventory management, stocking, and order processing\n\nKnowledge of stock control procedures and warehouse operations\n\nAttention to detail, organization, and teamwork skills",
    'Inventory Clerk' => "High school diploma or equivalent\n\nExperience in inventory management, stocking, and order processing\n\nKnowledge of stock control procedures and warehouse operations\n\nAttention to detail, organization, and teamwork skills",
    'Floor Manager' => "Bachelor's degree in Business or Retail Management (preferred)\n\nExperience managing store operations and staff on the sales floor\n\nKnowledge of merchandising, customer service, and store policies\n\nStrong leadership, communication, and multitasking skills",
    'Visual Merchandiser' => "Bachelor's degree in Design, Marketing, or related field (preferred)\n\nExperience in product display, visual presentation, and branding\n\nKnowledge of merchandising trends and marketing strategies\n\nCreativity, attention to detail, and organizational skills",
    'Sales Coordinator' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience coordinating sales activities, reporting, and support\n\nKnowledge of sales processes, customer management, and CRM tools\n\nStrong organizational, communication, and analytical skills",
    'Customer Service Associate' => "High school diploma or equivalent (Bachelor's preferred)\n\nExperience in customer service or support in a retail environment\n\nStrong communication, problem-solving, and interpersonal skills\n\nAbility to handle customer inquiries and complaints effectively",
    'Assistant Store Manager' => "Bachelor's degree in Business, Management, or related field\n\nExperience supporting store operations and supervising staff\n\nKnowledge of sales targets, inventory, and customer service\n\nLeadership, organizational, and problem-solving skills",
    'Key Account Executive' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience managing client accounts, sales, and customer relationships\n\nKnowledge of sales strategies, negotiation, and CRM tools\n\nStrong communication, analytical, and organizational skills",
    'Sales Representative' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience in retail or direct sales\n\nStrong communication, negotiation, and customer service skills\n\nGoal-oriented, organized, and persuasive",
    'Retail Sales Officer' => "Bachelor's degree in Business, Marketing, or related field\n\nExperience in sales, customer service, and retail operations\n\nKnowledge of products, sales techniques, and customer relations\n\nStrong interpersonal, communication, and problem-solving skills",
    'Shop Attendant' => "High school diploma or equivalent\n\nExperience assisting customers, handling products, or retail support\n\nBasic customer service and communication skills\n\nReliability, attentiveness, and teamwork",
    'Display Coordinator' => "High school diploma or Bachelor's in Marketing, Design, or related field (preferred)\n\nExperience creating in-store displays, signage, and merchandising layouts\n\nKnowledge of visual merchandising principles and trends\n\nCreativity, attention to detail, and organizational skills",
    // Transportation Qualifications
    'Driver' => "High school diploma or equivalent\n\nValid driver's license appropriate to vehicle type\n\nExperience driving safely and knowledge of traffic rules\n\nReliability, time management, and basic vehicle maintenance skills",
    'Delivery Rider' => "High school diploma or equivalent\n\nValid motorcycle license (for motorcycles)\n\nKnowledge of routes, safety, and traffic regulations\n\nReliability, punctuality, and basic customer service skills",
    'Fleet Manager' => "Bachelor's degree in Logistics, Transportation, or Business Management\n\nExperience managing a fleet of vehicles and drivers\n\nKnowledge of vehicle maintenance, scheduling, and cost management\n\nLeadership, organizational, and problem-solving skills",
    'Transport Coordinator' => "Bachelor's degree in Logistics, Supply Chain, or related field\n\nExperience in transportation scheduling, route planning, and documentation\n\nKnowledge of traffic regulations and transport management software\n\nStrong organizational, analytical, and communication skills",
    'Logistics Driver' => "High school diploma or equivalent\n\nValid driver's license appropriate for delivery trucks\n\nKnowledge of delivery procedures, routes, and traffic regulations\n\nPunctuality, reliability, and customer service skills",
    'Bus Driver' => "High school diploma or equivalent\n\nValid commercial driver's license\n\nExperience driving passenger buses safely and adhering to schedules\n\nKnowledge of traffic laws, route planning, and basic vehicle maintenance",
    'Taxi Driver' => "High school diploma or equivalent\n\nValid taxi or public transport license\n\nKnowledge of local roads, traffic regulations, and passenger service\n\nStrong communication and customer service skills",
    'Air Cargo Handler' => "High school diploma or vocational training\n\nKnowledge of cargo handling, logistics, and safety procedures\n\nPhysical fitness and teamwork skills\n\nAttention to detail and organizational skills",
    'Dispatch Officer' => "High school diploma or Bachelor's degree in Logistics, Transportation, or related field\n\nExperience coordinating drivers, deliveries, and routes\n\nKnowledge of dispatch systems, logistics, and customer service\n\nStrong communication, organizational, and problem-solving skills",
    'Vehicle Inspector' => "High school diploma or vocational certification in automotive technology\n\nExperience inspecting vehicles for safety, compliance, and maintenance\n\nKnowledge of mechanical systems, regulations, and safety standards\n\nAttention to detail, technical knowledge, and reporting skills",
    'Truck Driver' => "High school diploma or equivalent\n\nValid commercial truck driving license\n\nKnowledge of long-distance routes, vehicle maintenance, and safety procedures\n\nReliability, time management, and endurance",
    'Shuttle Driver' => "High school diploma or equivalent\n\nValid driver's license appropriate for shuttle or van vehicles\n\nKnowledge of routes, passenger safety, and traffic regulations\n\nPunctuality, reliability, and good interpersonal skills",
    'Transportation Officer' => "Bachelor's degree in Logistics, Transport Management, or related field\n\nExperience managing transportation operations and staff\n\nKnowledge of safety regulations, vehicle maintenance, and route planning\n\nLeadership, organizational, and analytical skills",
    'Delivery Supervisor' => "Bachelor's degree in Logistics, Supply Chain, or Business Management\n\nExperience supervising delivery staff and coordinating operations\n\nKnowledge of transport logistics, schedules, and customer service\n\nLeadership, communication, and problem-solving skills",
    // Law Enforcement / Criminology Qualifications
    'Police Officer' => "Bachelor's degree in Criminology, Police Science, or related field\n\nCompletion of Police Academy training\n\nKnowledge of law enforcement, criminal laws, and public safety procedures\n\nPhysical fitness, integrity, communication, and decision-making skills",
    'Detective' => "Bachelor's degree in Criminology, Criminal Justice, or related field\n\nExperience in investigation, evidence gathering, and law enforcement\n\nStrong analytical, observational, and problem-solving skills\n\nIntegrity, attention to detail, and discretion",
    'Crime Scene Investigator' => "Bachelor's degree in Forensic Science, Criminology, or related field\n\nKnowledge of evidence collection, preservation, and laboratory procedures\n\nAttention to detail, analytical thinking, and technical skills\n\nStrong report writing and documentation abilities",
    'Security Analyst' => "Bachelor's degree in Criminology, Security Management, or related field\n\nKnowledge of risk assessment, security protocols, and crime prevention\n\nAnalytical, problem-solving, and communication skills\n\nAwareness of current security threats and measures",
    'Forensic Specialist' => "Bachelor's degree in Forensic Science, Criminology, or Chemistry/Biology\n\nKnowledge of forensic techniques, lab procedures, and evidence analysis\n\nAttention to detail, analytical skills, and critical thinking\n\nAbility to prepare reports and testify in court",
    'Forensic Analyst' => "Bachelor's degree in Forensic Science, Criminology, or Chemistry/Biology\n\nKnowledge of forensic techniques, lab procedures, and evidence analysis\n\nAttention to detail, analytical skills, and critical thinking\n\nAbility to prepare reports and testify in court",
    'Corrections Officer' => "Bachelor's degree in Criminology, Criminal Justice, or related field\n\nKnowledge of correctional facility operations, inmate management, and safety protocols\n\nPhysical fitness, communication, and conflict resolution skills\n\nIntegrity, patience, and teamwork",
    'Crime Analyst' => "Bachelor's degree in Criminology, Criminal Justice, or Data Analytics\n\nKnowledge of crime trends, statistics, and reporting tools\n\nAnalytical thinking, attention to detail, and problem-solving skills\n\nAbility to prepare reports and assist law enforcement strategies",
    'Intelligence Officer' => "Bachelor's degree in Criminology, Criminal Justice, or Security Studies\n\nKnowledge of intelligence gathering, surveillance, and analysis techniques\n\nStrong analytical, research, and communication skills\n\nDiscretion, integrity, and critical thinking",
    'Patrol Officer' => "Bachelor's degree in Criminology, Police Science, or related field\n\nCompletion of Police Academy training\n\nKnowledge of patrolling procedures, public safety, and law enforcement\n\nPhysical fitness, situational awareness, and interpersonal skills",
    'Investigation Officer' => "Bachelor's degree in Criminology, Criminal Justice, or related field\n\nExperience in conducting investigations, interviewing, and evidence collection\n\nStrong analytical, observational, and communication skills\n\nAttention to detail, integrity, and problem-solving abilities",
    'Police Chief' => "Bachelor's degree in Criminology, Law Enforcement Administration, or related field\n\nExtensive experience in police operations, leadership, and management\n\nKnowledge of law enforcement policies, regulations, and public safety\n\nLeadership, decision-making, and organizational skills",
    'Detective Sergeant' => "Bachelor's degree in Criminology, Criminal Justice, or related field\n\nExperience in investigative procedures, evidence management, and law enforcement\n\nStrong leadership, analytical, and problem-solving skills\n\nIntegrity, discretion, and communication skills",
    'Crime Prevention Officer' => "Bachelor's degree in Criminology, Security Management, or related field\n\nKnowledge of community policing, crime prevention strategies, and public safety\n\nStrong communication, analytical, and organizational skills\n\nAbility to engage with the community and develop prevention programs",
    // Security Services Qualifications
    'Security Guard' => "High school diploma or equivalent\n\nTraining in basic security procedures and protocols\n\nKnowledge of emergency response, surveillance, and patrolling\n\nPhysical fitness, alertness, and communication skills",
    'Security Supervisor' => "High school diploma or Bachelor's degree in Security Management (preferred)\n\nExperience supervising security personnel and operations\n\nKnowledge of security protocols, risk assessment, and emergency procedures\n\nLeadership, decision-making, and communication skills",
    'Loss Prevention Officer' => "High school diploma or Bachelor's degree in Security, Criminal Justice, or related field\n\nExperience in retail or corporate loss prevention\n\nKnowledge of theft prevention, surveillance, and investigative techniques\n\nAttention to detail, integrity, and communication skills",
    'Bodyguard' => "High school diploma or Bachelor's degree (preferred)\n\nTraining in personal protection, self-defense, and security protocols\n\nExperience protecting VIPs, executives, or high-profile individuals\n\nPhysical fitness, alertness, discretion, and strong situational awareness",
    'Security Coordinator' => "Bachelor's degree in Security Management, Criminology, or related field\n\nExperience coordinating security operations, personnel, and schedules\n\nKnowledge of risk management, emergency planning, and compliance\n\nOrganizational, leadership, and communication skills",
    'Alarm Systems Officer' => "High school diploma or technical training in electronics/security systems\n\nKnowledge of alarm systems, surveillance equipment, and troubleshooting\n\nAbility to monitor and respond to security alerts\n\nTechnical skills, attention to detail, and problem-solving abilities",
    'CCTV Operator' => "High school diploma or vocational training in security systems\n\nExperience operating and monitoring CCTV systems\n\nKnowledge of surveillance procedures and incident reporting\n\nAttention to detail, alertness, and technical proficiency",
    'Security Consultant' => "Bachelor's degree in Security Management, Criminology, or related field\n\nExperience in security planning, risk assessment, and policy development\n\nKnowledge of security systems, protocols, and industry standards\n\nAnalytical, problem-solving, and communication skills",
    'Executive Protection Officer' => "High school diploma or Bachelor's degree (preferred)\n\nTraining in personal protection, self-defense, and security protocols\n\nExperience protecting VIPs, executives, or high-profile individuals\n\nPhysical fitness, alertness, discretion, and strong situational awareness",
    'Event Security Officer' => "High school diploma or Bachelor's degree (preferred)\n\nTraining or experience in crowd control, safety, and emergency response\n\nKnowledge of event security protocols and safety procedures\n\nPhysical fitness, alertness, and interpersonal skills",
    'Security Officer' => "High school diploma or equivalent\n\nTraining in basic security procedures and protocols\n\nKnowledge of emergency response, surveillance, and patrolling\n\nPhysical fitness, alertness, and communication skills",
    'Security Manager' => "Bachelor's degree in Security Management, Criminology, or related field\n\nExperience managing security teams and operations\n\nKnowledge of security policies, risk management, and compliance\n\nLeadership, strategic planning, and communication skills",
    'Safety and Security Officer' => "Bachelor's degree in Security Management, Occupational Safety, or related field\n\nKnowledge of workplace safety, emergency procedures, and security operations\n\nExperience in risk assessment and incident management\n\nAnalytical, detail-oriented, and leadership skills",
    // Skilled / Technical (TESDA) Qualifications
    'Electrician' => "High school diploma or TESDA NC II in Electrical Installation & Maintenance\n\nKnowledge of electrical wiring, circuits, and safety procedures\n\nAbility to read electrical diagrams and use hand/power tools\n\nProblem-solving, attention to detail, and physical dexterity",
    'Welder' => "High school diploma or TESDA NC II in Welding\n\nKnowledge of welding techniques (MIG, TIG, Arc) and safety practices\n\nAbility to read blueprints and metal fabrication plans\n\nPhysical stamina, precision, and attention to detail",
    'Automotive Technician' => "High school diploma or TESDA NC II in Automotive Servicing\n\nKnowledge of vehicle repair, diagnostics, and maintenance\n\nAbility to use tools and machinery for automotive repair\n\nProblem-solving skills, mechanical aptitude, and attention to detail",
    'Carpenter' => "High school diploma or TESDA NC II in Carpentry\n\nKnowledge of woodworking, construction, and furniture assembly\n\nAbility to read blueprints and use hand/power tools\n\nCreativity, physical stamina, and attention to detail",
    'Plumber' => "High school diploma or TESDA NC II in Plumbing\n\nKnowledge of water systems, pipe installation, and repair techniques\n\nAbility to read plumbing plans and troubleshoot issues\n\nPhysical stamina, problem-solving skills, and attention to detail",
    'Mason' => "High school diploma or TESDA NC II in Masonry\n\nKnowledge of concrete, bricklaying, and construction techniques\n\nAbility to follow construction plans and safety procedures\n\nPhysical stamina, precision, and teamwork",
    'HVAC Technician' => "High school diploma or TESDA NC II in Refrigeration & Air Conditioning\n\nKnowledge of HVAC systems, installation, and maintenance\n\nAbility to troubleshoot and repair heating/cooling systems\n\nTechnical skills, problem-solving, and attention to detail",
    'CNC Operator' => "High school diploma or TESDA NC II in Machining / CNC Operation\n\nKnowledge of CNC machines, tooling, and programming\n\nAbility to read technical drawings and operate machinery\n\nPrecision, technical aptitude, and problem-solving skills",
    'Industrial Technician' => "High school diploma or TESDA NC II in Industrial Technology\n\nKnowledge of machinery, maintenance, and industrial processes\n\nAbility to perform repairs and troubleshoot equipment\n\nTechnical skills, attention to detail, and safety awareness",
    'Electronics Technician' => "High school diploma or TESDA NC II in Electronics\n\nKnowledge of electronic circuits, devices, and troubleshooting\n\nAbility to read schematics and use testing equipment\n\nAnalytical skills, problem-solving, and attention to detail",
    'Refrigeration Technician' => "High school diploma or TESDA NC II in Refrigeration & Air Conditioning\n\nKnowledge of refrigeration systems, installation, and repair\n\nAbility to troubleshoot and maintain cooling units\n\nTechnical skills, problem-solving, and attention to detail",
    'Machinist' => "High school diploma or TESDA NC II in Machining\n\nKnowledge of metal cutting, shaping, and machine operation\n\nAbility to read blueprints and operate machine tools\n\nPrecision, technical skills, and safety awareness",
    'Fabricator' => "High school diploma or TESDA NC II in Fabrication / Welding\n\nKnowledge of metalwork, cutting, and assembly\n\nAbility to read designs and operate fabrication tools\n\nCreativity, precision, and technical skills",
    'Pipefitter' => "High school diploma or TESDA NC II in Pipefitting\n\nKnowledge of piping systems, installation, and maintenance\n\nAbility to read technical plans and use hand/power tools\n\nTechnical skills, precision, and safety awareness",
    'Maintenance Technician' => "High school diploma or TESDA NC II in Electrical, Mechanical, or Industrial Maintenance\n\nKnowledge of equipment maintenance, troubleshooting, and repair\n\nAbility to read manuals and follow safety procedures\n\nProblem-solving, technical skills, and attention to detail",
    'Tool and Die Maker' => "High school diploma or TESDA NC II in Tool and Die Making\n\nKnowledge of metal tooling, shaping, and assembly\n\nAbility to read technical drawings and operate machinery\n\nPrecision, technical skills, and problem-solving abilities",
    // Agriculture / Fisheries Qualifications
    'Farm Manager' => "Bachelor's degree in Agriculture, Agribusiness, or related field\n\nExperience managing farm operations, crops, and labor\n\nKnowledge of crop management, livestock care, and farm equipment\n\nLeadership, organizational, and problem-solving skills",
    'Agronomist' => "Bachelor's degree in Agriculture or Agronomy\n\nKnowledge of crop science, soil fertility, and pest management\n\nAbility to provide guidance on crop production and improvement\n\nAnalytical skills, attention to detail, and field experience",
    'Fishery Technician' => "Bachelor's degree in Fisheries, Aquaculture, or Marine Biology\n\nKnowledge of fish breeding, feeding, and water quality management\n\nAbility to monitor fish health and assist in aquaculture operations\n\nObservation skills, technical knowledge, and teamwork",
    'Agricultural Laborer' => "High school diploma or equivalent\n\nBasic knowledge of farm work, planting, and harvesting\n\nPhysical fitness and ability to perform manual labor\n\nReliability, teamwork, and willingness to learn",
    'Crop Specialist' => "Bachelor's degree in Agriculture or related field\n\nKnowledge of crop production, pest control, and irrigation techniques\n\nAbility to advise on crop selection, planting schedules, and yield improvement\n\nAnalytical skills, field experience, and attention to detail",
    'Livestock Technician' => "Bachelor's degree in Animal Science, Veterinary Technology, or Agriculture\n\nKnowledge of animal husbandry, feeding, breeding, and disease prevention\n\nAbility to assist veterinarians and monitor livestock health\n\nTechnical skills, observation, and problem-solving",
    'Farm Equipment Operator' => "High school diploma or vocational training in agricultural machinery\n\nKnowledge of operating tractors, harvesters, and other farm equipment\n\nAbility to perform basic maintenance and safety checks\n\nPhysical fitness, technical aptitude, and attention to safety",
    'Agriculture Extension Officer' => "Bachelor's degree in Agriculture, Agribusiness, or related field\n\nKnowledge of farming techniques, technology, and government programs\n\nAbility to advise farmers and conduct training programs\n\nCommunication, teaching, and problem-solving skills",
    'Horticulturist' => "Bachelor's degree in Horticulture, Agriculture, or Botany\n\nKnowledge of plant cultivation, landscaping, and garden management\n\nAbility to advise on plant care, pest management, and soil health\n\nCreativity, observation, and technical skills",
    'Aquaculture Specialist' => "Bachelor's degree in Aquaculture, Fisheries, or Marine Biology\n\nKnowledge of breeding, nutrition, and water quality management for aquatic species\n\nAbility to design and manage aquaculture systems\n\nAnalytical, technical, and problem-solving skills",
    'Plantation Supervisor' => "Bachelor's degree in Agriculture, Agronomy, or related field\n\nExperience in managing plantation operations and labor\n\nKnowledge of crop management, harvesting, and processing\n\nLeadership, organizational, and field management skills",
    'Farm Inspector' => "Bachelor's degree in Agriculture, Agronomy, or related field\n\nKnowledge of farm standards, quality control, and regulatory compliance\n\nAbility to conduct inspections and prepare reports\n\nAttention to detail, observation, and communication skills",
    'Soil Scientist' => "Bachelor's degree in Soil Science, Agriculture, or Environmental Science\n\nKnowledge of soil properties, fertility, and conservation methods\n\nAbility to analyze soil samples and recommend improvement measures\n\nAnalytical skills, technical expertise, and field experience",
    'Agriculture Technician' => "High school diploma or vocational training in Agriculture or related field\n\nKnowledge of farm operations, planting, harvesting, and equipment\n\nAbility to assist in research, monitoring, and data collection\n\nTechnical skills, observation, and teamwork",
    // Freelance / Online / Remote Qualifications
    'Virtual Assistant' => "High school diploma or Bachelor's degree (preferred)\n\nStrong organizational, communication, and time management skills\n\nProficiency in MS Office, Google Workspace, and online collaboration tools\n\nAbility to handle emails, scheduling, and basic administrative tasks remotely",
    'Freelance Writer' => "Bachelor's degree in English, Journalism, Communications, or related field (preferred)\n\nStrong writing, editing, and research skills\n\nAbility to write for different audiences and formats (blogs, articles, copywriting)\n\nSelf-motivation, meeting deadlines, and attention to detail",
    'Online Tutor' => "Bachelor's degree in Education or the subject area being taught\n\nTeaching experience and knowledge of online teaching platforms\n\nGood communication and interpersonal skills\n\nPatience, adaptability, and ability to explain concepts clearly",
    'Graphic Designer' => "Bachelor's degree in Graphic Design, Fine Arts, or related field (preferred)\n\nProficiency in Adobe Creative Suite (Photoshop, Illustrator, InDesign)\n\nCreativity, visual storytelling, and attention to detail\n\nAbility to work independently and meet client requirements remotely",
    'Content Creator' => "High school diploma or Bachelor's degree in Communications, Marketing, or related field (preferred)\n\nCreativity in producing digital content (videos, images, social media posts)\n\nKnowledge of social media platforms and analytics tools\n\nSelf-motivation, time management, and adaptability",
    'Social Media Manager' => "Bachelor's degree in Marketing, Communications, or related field\n\nKnowledge of social media platforms, trends, and content strategy\n\nAbility to schedule posts, analyze metrics, and engage with audiences\n\nCommunication skills, creativity, and digital marketing knowledge",
    'Web Developer' => "Bachelor's degree in Computer Science, IT, or related field (preferred)\n\nKnowledge of HTML, CSS, JavaScript, and web frameworks\n\nExperience in building responsive and functional websites\n\nProblem-solving, analytical skills, and attention to detail",
    'Data Entry Specialist' => "High school diploma or Bachelor's degree (preferred)\n\nFast and accurate typing skills\n\nKnowledge of MS Office, Google Sheets, and data management\n\nAttention to detail, organization, and time management",
    'Translator' => "Bachelor's degree in Languages, Linguistics, or related field\n\nFluency in source and target languages\n\nExcellent written communication and proofreading skills\n\nAttention to cultural nuances and deadlines",
    'Remote Customer Support' => "High school diploma or Bachelor's degree (preferred)\n\nExperience in customer service or BPO environment\n\nGood communication and problem-solving skills\n\nAbility to use CRM software and handle customer inquiries remotely",
    'Online Consultant' => "Bachelor's degree in relevant field depending on consultancy area\n\nExpertise in a specific industry or subject matter\n\nStrong analytical, advisory, and communication skills\n\nAbility to work independently and provide guidance virtually",
    'SEO Specialist' => "Bachelor's degree in Marketing, IT, or related field (preferred)\n\nKnowledge of search engine optimization, keywords, and analytics\n\nAbility to optimize websites and content for search engines\n\nAnalytical thinking, attention to detail, and technical SEO knowledge",
    'Digital Marketing Freelancer' => "Bachelor's degree in Marketing, Communications, or related field (preferred)\n\nKnowledge of digital marketing tools, campaigns, and social media\n\nAbility to create, manage, and analyze online marketing campaigns\n\nSelf-motivation, creativity, and project management skills",
    'Video Editor – Remote' => "High school diploma or Bachelor's degree in Multimedia, Film, or related field (preferred)\n\nProficiency in video editing software (Adobe Premiere, Final Cut Pro)\n\nCreativity in storytelling and post-production\n\nAttention to detail, time management, and ability to follow client briefs",
    // Legal / Government / Public Service Qualifications
    'Lawyer' => "Bachelor's degree in Law (LLB/JD)\n\nPassed the Bar Exam and licensed to practice law\n\nKnowledge of legal procedures, case law, and contracts\n\nStrong analytical, research, and communication skills",
    'Paralegal' => "Bachelor's degree in Law, Legal Studies, or related field\n\nKnowledge of legal terminology, document preparation, and court procedures\n\nStrong research, organization, and writing skills\n\nAbility to assist lawyers in case preparation",
    'Government Officer' => "Bachelor's degree in Public Administration, Political Science, or related field\n\nKnowledge of government policies, procedures, and regulations\n\nStrong administrative, communication, and organizational skills\n\nAnalytical thinking and public service orientation",
    'Legal Assistant' => "Bachelor's degree in Law or Legal Studies (preferred)\n\nKnowledge of legal documents, case management, and office procedures\n\nStrong communication, organization, and computer skills\n\nAbility to support lawyers or legal teams",
    'Policy Analyst' => "Bachelor's degree in Public Policy, Political Science, or related field\n\nKnowledge of policy research, evaluation, and development\n\nStrong analytical, writing, and communication skills\n\nAbility to provide recommendations to decision-makers",
    'Court Clerk' => "Bachelor's degree in Law, Paralegal Studies, or related field\n\nKnowledge of court procedures and legal documentation\n\nStrong organizational, record-keeping, and communication skills\n\nAttention to detail and confidentiality",
    'Compliance Officer' => "Bachelor's degree in Law, Business, or related field\n\nKnowledge of laws, regulations, and compliance procedures\n\nStrong analytical, research, and reporting skills\n\nAbility to monitor and enforce regulatory compliance",
    'Public Administrator' => "Bachelor's degree in Public Administration or related field\n\nKnowledge of governance, public policies, and administration\n\nStrong leadership, organizational, and communication skills\n\nAbility to manage public programs and services",
    'Legal Researcher' => "Bachelor's degree in Law or Legal Studies\n\nKnowledge of legal research methods, case law, and statutes\n\nStrong analytical, writing, and critical thinking skills\n\nAttention to detail and ability to summarize complex information",
    'Legal Consultant' => "Bachelor's degree in Law or related field\n\nExtensive knowledge and experience in specific areas of law\n\nStrong advisory, analytical, and communication skills\n\nAbility to provide strategic legal guidance to clients or organizations",
    'Judicial Clerk' => "Bachelor's degree in Law\n\nKnowledge of court operations, legal research, and case management\n\nStrong analytical, writing, and organizational skills\n\nAbility to assist judges in preparing opinions and decisions",
    'Public Policy Officer' => "Bachelor's degree in Public Policy, Political Science, or related field\n\nKnowledge of policy development, research, and evaluation\n\nStrong analytical, communication, and problem-solving skills\n\nAbility to coordinate and implement public programs",
    'Court Officer' => "Bachelor's degree in Law, Paralegal Studies, or related field\n\nKnowledge of court procedures, security protocols, and documentation\n\nStrong organizational, communication, and observation skills\n\nAbility to manage court operations and assist in hearings",
    'Administrative Law Officer' => "Bachelor's degree in Law or Public Administration\n\nKnowledge of administrative laws, regulations, and compliance\n\nStrong research, analytical, and organizational skills\n\nAbility to review and advise on administrative legal matters",
    // Maritime / Aviation / Transport Specialized Qualifications
    'Ship Captain' => "Bachelor's degree in Marine Transportation or related field\n\nCertificate of Competency (COC) as Master Mariner\n\nExtensive sea experience and knowledge of navigation, safety, and maritime laws\n\nLeadership, decision-making, and crisis management skills",
    'Pilot' => "Bachelor's degree in Aviation or Aeronautical Engineering (preferred)\n\nCommercial Pilot License (CPL) or Airline Transport Pilot License (ATPL)\n\nExtensive flight hours and experience in aircraft operation\n\nStrong decision-making, situational awareness, and communication skills",
    'Flight Attendant' => "High school diploma or Bachelor's degree (preferred)\n\nCertification in flight safety and emergency procedures\n\nExcellent communication, customer service, and interpersonal skills\n\nAbility to handle emergencies and ensure passenger safety",
    'Marine Engineer' => "Bachelor's degree in Marine Engineering or Mechanical Engineering\n\nKnowledge of ship engines, machinery, and maintenance procedures\n\nCertification in maritime engineering and safety\n\nProblem-solving, technical, and teamwork skills",
    'Deck Officer' => "Bachelor's degree in Marine Transportation or related field\n\nCertificate of Competency (COC) as Officer of the Watch\n\nKnowledge of ship navigation, cargo handling, and safety procedures\n\nLeadership, communication, and decision-making skills",
    'Air Traffic Controller' => "Bachelor's degree in Aviation or Air Traffic Management\n\nCertification from Civil Aviation Authority\n\nKnowledge of airspace regulations, radar systems, and communication protocols\n\nStrong concentration, decision-making, and stress management skills",
    'Ship Engineer' => "Bachelor's degree in Marine Engineering or Mechanical Engineering\n\nCertification in maritime engineering and maintenance\n\nKnowledge of ship systems, engines, and safety protocols\n\nProblem-solving, technical, and teamwork skills",
    'Cabin Crew' => "High school diploma or Bachelor's degree (preferred)\n\nTraining in flight safety, first aid, and customer service\n\nExcellent communication, interpersonal, and problem-solving skills\n\nAbility to handle emergencies and ensure passenger comfort",
    'Marine Technician' => "Bachelor's degree or vocational training in Marine Engineering/Technology\n\nKnowledge of ship machinery, electronics, and maintenance procedures\n\nTechnical certification in marine systems (preferred)\n\nAnalytical, troubleshooting, and teamwork skills",
    'Aviation Safety Officer' => "Bachelor's degree in Aviation, Aeronautical Engineering, or Safety Management\n\nCertification in aviation safety and risk management\n\nKnowledge of safety regulations, audits, and incident investigation\n\nAnalytical, observational, and communication skills",
    'Port Officer' => "Bachelor's degree in Maritime Studies, Logistics, or Business Administration\n\nKnowledge of port operations, shipping procedures, and customs regulations\n\nStrong organizational, communication, and coordination skills\n\nAbility to manage port logistics efficiently",
    'Harbor Master' => "Bachelor's degree in Maritime Studies, Marine Transportation, or related field\n\nExtensive experience in port operations, navigation, and maritime law\n\nLeadership, decision-making, and crisis management skills\n\nKnowledge of safety, regulations, and harbor logistics",
    'Flight Dispatcher' => "Bachelor's degree in Aviation, Aeronautical Engineering, or related field\n\nCertification in flight dispatching\n\nKnowledge of flight planning, weather analysis, and air traffic regulations\n\nStrong analytical, organizational, and communication skills",
    // Science / Research / Environment Qualifications
    'Research Scientist' => "Bachelor's or Master's degree in relevant scientific field (Biology, Chemistry, Physics, Environmental Science, etc.)\n\nStrong analytical, research, and problem-solving skills\n\nExperience with laboratory techniques, experiments, and data analysis\n\nAbility to write scientific reports and publish findings",
    'Laboratory Technician' => "Bachelor's degree in Biology, Chemistry, Medical Technology, or related field\n\nKnowledge of laboratory procedures, safety protocols, and equipment handling\n\nAttention to detail, accuracy, and organizational skills\n\nAbility to prepare samples, conduct tests, and record results",
    'Environmental Officer' => "Bachelor's degree in Environmental Science, Ecology, or related field\n\nKnowledge of environmental laws, regulations, and sustainability practices\n\nStrong analytical, reporting, and project management skills\n\nAbility to conduct environmental assessments and audits",
    'Data Analyst' => "Bachelor's degree in Statistics, Mathematics, Computer Science, or related field\n\nKnowledge of data analysis tools (Excel, SQL, Python, R, etc.)\n\nStrong analytical, problem-solving, and reporting skills\n\nAbility to interpret complex datasets and provide actionable insights",
    'Biochemist' => "Bachelor's or Master's degree in Biochemistry, Molecular Biology, or related field\n\nKnowledge of laboratory techniques, molecular assays, and data analysis\n\nStrong analytical, research, and problem-solving skills\n\nAbility to document findings and collaborate on scientific projects",
    'Ecologist' => "Bachelor's degree in Ecology, Environmental Science, or Biology\n\nKnowledge of ecosystems, biodiversity, and environmental monitoring\n\nFieldwork experience, data collection, and analysis skills\n\nStrong communication and reporting abilities",
    'Field Researcher' => "Bachelor's degree in relevant scientific or environmental field\n\nExperience with fieldwork, sampling, and data collection\n\nKnowledge of safety procedures and research methodologies\n\nAbility to document and analyze field observations",
    'Microbiologist' => "Bachelor's or Master's degree in Microbiology, Biology, or related field\n\nKnowledge of laboratory techniques, microbial cultures, and safety protocols\n\nAnalytical, research, and problem-solving skills\n\nAbility to prepare scientific reports and maintain lab records",
    'Environmental Consultant' => "Bachelor's degree in Environmental Science, Engineering, or related field\n\nKnowledge of environmental regulations, impact assessments, and sustainability practices\n\nStrong analytical, reporting, and advisory skills\n\nAbility to provide recommendations to clients on environmental management",
    'Lab Assistant' => "High school diploma or Bachelor's degree in relevant scientific field (preferred)\n\nKnowledge of laboratory safety and basic lab procedures\n\nAttention to detail, organizational, and record-keeping skills\n\nAbility to assist in experiments, sample preparation, and equipment maintenance",
    'Research Assistant' => "Bachelor's degree in Science, Research, or related field\n\nExperience in laboratory or field research\n\nStrong analytical, data collection, and reporting skills\n\nAbility to assist lead researchers in projects and experiments",
    'Marine Biologist' => "Bachelor's degree in Marine Biology, Environmental Science, or Biology\n\nKnowledge of marine ecosystems, research methods, and conservation practices\n\nFieldwork experience and laboratory skills\n\nAnalytical, reporting, and project management skills",
    'Laboratory Analyst' => "Bachelor's degree in Chemistry, Biology, or related scientific field\n\nKnowledge of lab procedures, instrumentation, and quality control\n\nStrong analytical, documentation, and problem-solving skills\n\nAbility to interpret test results accurately",
    'Climate Scientist' => "Bachelor's or Master's degree in Climate Science, Meteorology, Environmental Science, or related field\n\nKnowledge of climate modeling, data analysis, and environmental monitoring\n\nAnalytical, research, and reporting skills\n\nAbility to study climate trends and provide actionable insights",
    // Arts / Entertainment / Culture Qualifications
    'Actor' => "Bachelor's degree in Theater, Performing Arts, or related field (preferred)\n\nTraining in acting techniques, stage performance, and character development\n\nStrong communication, creativity, and emotional expression skills\n\nAbility to work under direction and collaborate with cast and crew",
    'Musician' => "Bachelor's degree in Music, Music Performance, or related field (preferred)\n\nProficiency in playing instruments or vocal performance\n\nKnowledge of music theory, composition, and performance techniques\n\nCreativity, discipline, and ability to perform live or in studio",
    'Dancer' => "Bachelor's degree in Dance, Performing Arts, or related field (preferred)\n\nTraining in dance techniques, choreography, and performance\n\nPhysical stamina, rhythm, and flexibility\n\nAbility to perform in stage productions, shows, or competitions",
    'Cultural Program Coordinator' => "Bachelor's degree in Arts, Culture, or Event Management\n\nKnowledge of cultural programs, heritage, and community engagement\n\nStrong organizational, communication, and project management skills\n\nAbility to coordinate events, workshops, and cultural activities",
    'Singer' => "Bachelor's degree in Music, Vocal Performance, or related field (preferred)\n\nVocal training, performance experience, and music theory knowledge\n\nStage presence, creativity, and emotional expression skills\n\nAbility to perform solo or with ensembles",
    'Director' => "Bachelor's degree in Film, Theater, Performing Arts, or related field\n\nKnowledge of production, storytelling, and project management\n\nStrong leadership, creative vision, and communication skills\n\nAbility to direct actors, crew, and overall production",
    'Photographer' => "Bachelor's degree in Photography, Visual Arts, or related field (preferred)\n\nProficiency with cameras, lighting, and editing software\n\nCreative, artistic, and detail-oriented\n\nAbility to capture images for events, media, or artistic projects",
    'Art Curator' => "Bachelor's degree in Art History, Fine Arts, or Museum Studies\n\nKnowledge of art preservation, curation, and exhibition management\n\nStrong research, organizational, and communication skills\n\nAbility to curate art collections, exhibits, and cultural programs",
    'Theater Performer' => "Bachelor's degree in Performing Arts, Theater, or related field (preferred)\n\nTraining in acting, singing, or dance techniques\n\nCreativity, stage presence, and teamwork skills\n\nAbility to perform live on stage or in productions",
    'Costume Designer' => "Bachelor's degree in Fashion Design, Costume Design, or related field\n\nKnowledge of textiles, costume construction, and design principles\n\nCreativity, attention to detail, and problem-solving skills\n\nAbility to design costumes for theater, film, or events",
    'Visual Artist' => "Bachelor's degree in Fine Arts, Visual Arts, or related field\n\nProficiency in painting, drawing, sculpture, or digital art\n\nCreativity, innovation, and attention to detail\n\nAbility to produce artworks for exhibitions, commissions, or sale",
    'Film Editor' => "Bachelor's degree in Film, Multimedia, or related field\n\nProficiency in editing software (Adobe Premiere, Final Cut, etc.)\n\nKnowledge of storytelling, pacing, and post-production\n\nCreativity, attention to detail, and technical skills",
    'Choreographer' => "Bachelor's degree in Dance, Performing Arts, or related field\n\nExpertise in dance techniques, choreography, and performance\n\nCreativity, leadership, and ability to instruct dancers\n\nAbility to design and coordinate dance performances",
    'Stage Manager' => "Bachelor's degree in Theater, Performing Arts, or related field (preferred)\n\nKnowledge of stage operations, production coordination, and scheduling\n\nStrong organizational, communication, and leadership skills\n\nAbility to manage rehearsals, performances, and backstage operations",
    // Religion / NGO / Development / Cooperative Qualifications
    'Pastor' => "Bachelor's degree in Theology, Divinity, or Religious Studies\n\nOrdination or certification from recognized religious organization\n\nStrong leadership, communication, and counseling skills\n\nAbility to conduct services, preach, and guide congregation",
    'NGO Program Officer' => "Bachelor's degree in Social Work, Development Studies, or related field\n\nKnowledge of project management, community development, and program evaluation\n\nStrong organizational, reporting, and interpersonal skills\n\nAbility to coordinate with stakeholders and implement programs",
    'Social Worker' => "Bachelor's degree in Social Work (BSW) or related field\n\nLicensed Social Worker (LSW) certification preferred\n\nKnowledge of social services, counseling, and case management\n\nStrong empathy, communication, and problem-solving skills",
    'Community Organizer' => "Bachelor's degree in Community Development, Sociology, or related field\n\nKnowledge of grassroots mobilization, advocacy, and community engagement\n\nStrong interpersonal, organizational, and leadership skills\n\nAbility to plan and execute community programs",
    'Missionary' => "Bachelor's degree in Theology, Divinity, Religious Studies, or Social Work\n\nStrong communication, cross-cultural, and counseling skills\n\nAbility to organize and lead religious or social programs\n\nAdaptability to different environments and communities",
    'Development Officer' => "Bachelor's degree in Development Studies, Social Work, or related field\n\nKnowledge of fundraising, program development, and community outreach\n\nStrong communication, planning, and project management skills\n\nAbility to monitor and evaluate development initiatives",
    'Volunteer Coordinator' => "Bachelor's degree in Social Work, Human Resources, or related field\n\nExperience in volunteer management and community programs\n\nStrong organizational, interpersonal, and leadership skills\n\nAbility to recruit, train, and manage volunteers",
    'Church Administrator' => "Bachelor's degree in Business Administration, Management, or related field\n\nKnowledge of church operations, finance, and administrative procedures\n\nStrong organizational, communication, and leadership skills\n\nAbility to manage staff, budgets, and events",
    'Program Manager' => "Bachelor's degree in Development Studies, Business Administration, or related field\n\nExperience in project management, planning, and evaluation\n\nStrong leadership, organizational, and communication skills\n\nAbility to oversee programs from conception to completion",
    'Cooperative Manager' => "Bachelor's degree in Business Administration, Cooperative Management, or related field\n\nKnowledge of cooperative operations, finance, and governance\n\nStrong leadership, analytical, and problem-solving skills\n\nAbility to manage members, resources, and programs",
    'Field Officer – NGO' => "Bachelor's degree in Social Work, Community Development, or related field\n\nKnowledge of field operations, project implementation, and reporting\n\nStrong interpersonal, communication, and problem-solving skills\n\nAbility to work in diverse community settings",
    'Project Officer – NGO' => "Bachelor's degree in Development Studies, Social Work, or related field\n\nKnowledge of project planning, monitoring, and evaluation\n\nStrong organizational, communication, and reporting skills\n\nAbility to support program implementation and stakeholder coordination",
    'Community Development Officer' => "Bachelor's degree in Community Development, Social Work, or related field\n\nKnowledge of community needs assessment, program design, and monitoring\n\nStrong interpersonal, leadership, and analytical skills\n\nAbility to develop and implement community initiatives",
    // Special / Rare Jobs Qualifications
    'Ethical Hacker' => "Bachelor's degree in Computer Science, Information Technology, or Cybersecurity\n\nCertifications like CEH (Certified Ethical Hacker) preferred\n\nKnowledge of penetration testing, network security, and cybersecurity protocols\n\nStrong problem-solving, analytical, and critical-thinking skills",
    'Stunt Performer' => "Training or degree in Performing Arts, Physical Education, or related field\n\nExperience in stunts, acrobatics, martial arts, or extreme sports\n\nStrong physical fitness, coordination, and safety awareness\n\nAbility to perform stunts safely under direction",
    'Ice Sculptor' => "Training in Fine Arts, Sculpture, or related artistic field\n\nKnowledge of ice sculpting tools, techniques, and safety practices\n\nCreativity, precision, and artistic vision\n\nAbility to design and execute ice sculptures for events or displays",
    'Professional Gamer' => "Experience in competitive gaming, esports, or streaming\n\nStrong strategic thinking, reflexes, and hand-eye coordination\n\nKnowledge of gaming platforms, trends, and team dynamics\n\nAbility to compete, collaborate, and engage audiences online",
    'Escape Room Designer' => "Bachelor's degree in Game Design, Architecture, or related field (preferred)\n\nCreativity in puzzle design, storytelling, and immersive experiences\n\nStrong problem-solving, project management, and teamwork skills\n\nAbility to design, test, and implement escape room scenarios",
    'Drone Operator' => "Certification in UAV/drone operation (if required by local regulations)\n\nKnowledge of drone technology, navigation, and safety regulations\n\nStrong hand-eye coordination, spatial awareness, and technical skills\n\nAbility to capture aerial footage or conduct surveys",
    'Voice Actor' => "Training in Voice Acting, Performing Arts, or Communications\n\nClear diction, vocal range, and ability to convey emotions\n\nExperience in recording studios or audio production preferred\n\nAbility to perform scripts for animation, commercials, or audiobooks",
    'Extreme Sports Instructor' => "Certification in relevant extreme sports (skydiving, rock climbing, etc.)\n\nKnowledge of safety procedures, equipment, and techniques\n\nStrong physical fitness, leadership, and teaching skills\n\nAbility to train and supervise clients safely",
    'Special Effects Artist' => "Bachelor's degree in Film, Animation, or Visual Effects (preferred)\n\nKnowledge of CGI, makeup, props, and special effects techniques\n\nCreativity, attention to detail, and technical proficiency\n\nAbility to design and implement effects for film, theater, or media",
    'Magician' => "Training or experience in Magic, Performing Arts, or Entertainment\n\nKnowledge of sleight-of-hand, illusions, and stage performance\n\nCreativity, showmanship, and audience engagement skills\n\nAbility to design, perform, and entertain live or online audiences",
    'Mystery Shopper' => "High school diploma or equivalent (Bachelor's preferred)\n\nKnowledge of retail, customer service standards, and evaluation techniques\n\nAttention to detail, observation, and reporting skills\n\nAbility to provide objective feedback on products or services",
    'Puppeteer' => "Training or degree in Theater, Performing Arts, or Puppetry\n\nKnowledge of puppet manipulation, storytelling, and stage performance\n\nCreativity, dexterity, and coordination skills\n\nAbility to perform in live shows, events, or educational programs",
    'Forensic Artist' => "Bachelor's degree in Fine Arts, Criminal Justice, or Forensic Science\n\nKnowledge of anatomy, facial reconstruction, and law enforcement protocols\n\nStrong drawing, observation, and analytical skills\n\nAbility to produce sketches for criminal investigations",
    // Utilities / Public Services Qualifications
    'Electrician' => "Vocational/Technical certificate or Bachelor's in Electrical Engineering (preferred)\n\nKnowledge of electrical systems, wiring, and safety protocols\n\nTechnical skills in installation, maintenance, and troubleshooting\n\nAbility to work independently or in teams safely",
    'Water Plant Operator' => "Vocational/Technical certificate or Bachelor's in Environmental/Mechanical/Civil Engineering (preferred)\n\nKnowledge of water treatment processes, pumps, and safety regulations\n\nAbility to monitor, operate, and maintain water treatment equipment\n\nStrong problem-solving and attention-to-detail skills",
    'Utility Technician' => "Vocational/Technical certificate or relevant engineering background\n\nKnowledge of utility systems: electricity, water, or gas\n\nTechnical and troubleshooting skills for field operations\n\nAbility to perform maintenance, inspection, and repairs safely",
    'Meter Reader' => "High school diploma or vocational/technical training\n\nKnowledge of electricity/water meters and measurement systems\n\nAccuracy, attention to detail, and reliability\n\nAbility to work independently in field conditions",
    'Waste Management Officer' => "Bachelor's degree in Environmental Science, Sanitation, or related field\n\nKnowledge of waste collection, recycling, and disposal regulations\n\nStrong organizational, monitoring, and compliance skills\n\nAbility to supervise waste management operations",
    'Line Worker' => "Vocational/Technical certificate or training in electrical systems\n\nKnowledge of high-voltage lines, safety standards, and maintenance procedures\n\nPhysical stamina, problem-solving, and teamwork skills\n\nAbility to repair and maintain power lines safely",
    'Public Utility Engineer' => "Bachelor's degree in Civil, Electrical, or Mechanical Engineering\n\nKnowledge of utility systems design, operation, and maintenance\n\nProject management, analytical, and technical skills\n\nAbility to plan, implement, and monitor public utility projects",
    'Maintenance Technician' => "Vocational/Technical certificate or related engineering/technical background\n\nKnowledge of mechanical, electrical, or facility maintenance\n\nTroubleshooting, repair, and preventive maintenance skills\n\nAbility to work safely and efficiently in facilities",
    'Facility Officer' => "Bachelor's degree in Facility Management, Engineering, or related field\n\nKnowledge of facility operations, safety protocols, and compliance\n\nOrganizational, monitoring, and problem-solving skills\n\nAbility to coordinate staff, vendors, and maintenance schedules",
    'Energy Technician' => "Vocational/Technical certificate or Bachelor's in Energy, Electrical, or Mechanical Engineering\n\nKnowledge of energy systems, monitoring, and optimization\n\nTechnical skills in installation, operation, and maintenance\n\nAbility to troubleshoot and maintain energy systems efficiently",
    'Water Treatment Technician' => "Vocational/Technical certificate or Bachelor's in Environmental Science/Engineering\n\nKnowledge of water treatment processes, equipment, and safety regulations\n\nTechnical skills in monitoring, operation, and maintenance\n\nAbility to ensure water quality and regulatory compliance",
    'Power Plant Operator' => "Bachelor's degree in Mechanical, Electrical, or Energy Engineering (preferred)\n\nKnowledge of power generation, turbines, boilers, and safety standards\n\nTechnical skills in operating, monitoring, and maintaining power plant equipment\n\nAbility to respond to emergencies and maintain continuous power supply",
    // Telecommunications Qualifications
    'Telecommunications Technician' => "Vocational/Technical certificate or Bachelor's in Electronics/Telecommunications Engineering\n\nKnowledge of telecom equipment, installation, and maintenance\n\nTechnical troubleshooting and repair skills\n\nAbility to follow safety protocols and work in field conditions",
    'Network Engineer' => "Bachelor's degree in Computer Science, Information Technology, or Telecommunications\n\nKnowledge of network infrastructure, routing, switching, and protocols\n\nCertifications like CCNA, CCNP preferred\n\nProblem-solving, analytical, and project management skills",
    'Customer Support Specialist' => "Bachelor's degree or relevant vocational/technical background\n\nKnowledge of telecommunications products and services\n\nStrong communication, problem-solving, and customer service skills\n\nAbility to handle customer queries and technical issues",
    'Field Engineer' => "Bachelor's degree in Electronics, Telecommunications, or Electrical Engineering\n\nKnowledge of telecom network installation, testing, and troubleshooting\n\nTechnical skills for equipment setup and maintenance\n\nAbility to work on-site in various locations",
    'Tower Technician' => "Vocational/Technical certificate or Bachelor's in Electronics/Telecommunications\n\nKnowledge of telecom towers, antennas, and safety procedures\n\nAbility to perform installations, inspections, and maintenance at heights\n\nPhysical fitness and adherence to safety standards",
    'Telecom Analyst' => "Bachelor's degree in Information Technology, Telecommunications, or related field\n\nKnowledge of network performance, monitoring, and optimization\n\nAnalytical, problem-solving, and reporting skills\n\nAbility to evaluate telecom systems and suggest improvements",
    'Fiber Optic Technician' => "Vocational/Technical certificate or Bachelor's in Telecommunications/Electronics\n\nKnowledge of fiber optic cable installation, splicing, and testing\n\nTechnical skills and attention to detail\n\nAbility to troubleshoot and maintain fiber optic networks",
    'VoIP Specialist' => "Bachelor's degree in IT, Telecommunications, or related field\n\nKnowledge of VoIP systems, protocols, and network configuration\n\nTroubleshooting, installation, and maintenance skills\n\nAbility to manage IP telephony and communication systems",
    'RF Engineer' => "Bachelor's degree in Electronics, Telecommunications, or Electrical Engineering\n\nKnowledge of RF systems, signal propagation, and network planning\n\nAnalytical, problem-solving, and project management skills\n\nAbility to design, optimize, and maintain RF networks",
    'Service Coordinator' => "Bachelor's degree in Business Administration, IT, or Telecommunications\n\nKnowledge of telecom services, operations, and client coordination\n\nStrong organizational, communication, and problem-solving skills\n\nAbility to manage schedules, requests, and service delivery",
    'Telecom Sales Officer' => "Bachelor's degree in Marketing, Business Administration, or related field\n\nKnowledge of telecom products, services, and market trends\n\nStrong communication, negotiation, and customer service skills\n\nAbility to achieve sales targets and build client relationships",
    'Network Installation Technician' => "Vocational/Technical certificate or Bachelor's in IT/Electronics/Telecommunications\n\nKnowledge of network installation, cabling, and configuration\n\nTroubleshooting, testing, and maintenance skills\n\nAbility to work in field conditions and follow safety protocols",
    // Mining / Geology Qualifications
    'Geologist' => "Bachelor's degree in Geology, Earth Science, or related field\n\nKnowledge of mineral exploration, rock formations, and geological surveys\n\nStrong analytical, mapping, and field research skills\n\nAbility to conduct fieldwork and prepare technical reports",
    'Mining Engineer' => "Bachelor's degree in Mining Engineering or related field\n\nKnowledge of mining methods, operations, and safety protocols\n\nTechnical, analytical, and project management skills\n\nAbility to plan, supervise, and optimize mining operations",
    'Drill Operator' => "Vocational/Technical certificate or experience in mining operations\n\nKnowledge of drilling equipment, safety standards, and site operations\n\nPhysical fitness, attention to detail, and technical skills\n\nAbility to operate and maintain drilling machinery safely",
    'Safety Officer' => "Bachelor's degree in Occupational Health & Safety, Engineering, or related field\n\nKnowledge of mining safety regulations, procedures, and risk assessment\n\nStrong analytical, observation, and communication skills\n\nAbility to monitor, enforce, and train staff on safety protocols",
    'Surveyor' => "Bachelor's degree in Geomatics, Civil Engineering, or Surveying\n\nKnowledge of surveying techniques, GPS, and mapping tools\n\nStrong mathematical, analytical, and fieldwork skills\n\nAbility to measure, record, and interpret site data accurately",
    'Mine Technician' => "Vocational/Technical certificate or Bachelor's in Mining Engineering or related field\n\nKnowledge of mining equipment, maintenance, and operations\n\nTechnical, problem-solving, and safety skills\n\nAbility to assist engineers and conduct site inspections",
    'Geotechnical Engineer' => "Bachelor's degree in Civil or Geotechnical Engineering\n\nKnowledge of soil mechanics, rock analysis, and foundation design\n\nAnalytical, technical, and fieldwork skills\n\nAbility to assess ground conditions for mining or construction projects",
    'Mineral Analyst' => "Bachelor's degree in Geology, Chemistry, or Mineral Processing\n\nKnowledge of mineral composition, testing methods, and lab techniques\n\nStrong analytical, technical, and report-writing skills\n\nAbility to perform mineral assays and quality analysis",
    'Exploration Officer' => "Bachelor's degree in Geology, Mining Engineering, or related field\n\nKnowledge of exploration methods, mineral deposits, and geological surveys\n\nStrong analytical, project management, and fieldwork skills\n\nAbility to plan, coordinate, and supervise exploration projects",
    'Quarry Supervisor' => "Vocational/Technical certificate or Bachelor's in Mining/Construction/Engineering\n\nKnowledge of quarry operations, machinery, and safety regulations\n\nStrong leadership, organizational, and technical skills\n\nAbility to oversee daily operations, production, and safety compliance",
    'Mine Surveyor' => "Bachelor's degree in Geomatics, Surveying, or Civil Engineering\n\nKnowledge of mine mapping, surveying equipment, and data interpretation\n\nAnalytical, mathematical, and fieldwork skills\n\nAbility to produce accurate mine plans and reports",
    'Mining Safety Engineer' => "Bachelor's degree in Mining Engineering, Safety Engineering, or Occupational Health\n\nKnowledge of mining safety regulations, risk assessment, and accident prevention\n\nAnalytical, problem-solving, and training skills\n\nAbility to design, implement, and monitor safety programs in mines",
    // Oil / Gas / Energy Qualifications
    'Petroleum Engineer' => "Bachelor's degree in Petroleum Engineering, Mechanical Engineering, or Chemical Engineering\n\nKnowledge of oil and gas extraction, drilling methods, and reservoir management\n\nStrong analytical, technical, and problem-solving skills\n\nAbility to design and optimize production processes",
    'Safety Officer' => "Bachelor's degree in Occupational Health & Safety, Engineering, or related field\n\nKnowledge of safety regulations and procedures specific to oil & gas industry\n\nStrong observation, analytical, and communication skills\n\nAbility to monitor compliance, conduct inspections, and train personnel",
    'Energy Analyst' => "Bachelor's degree in Energy Management, Electrical/Mechanical Engineering, or Economics\n\nKnowledge of energy markets, production, and sustainability\n\nStrong analytical, data interpretation, and reporting skills\n\nAbility to evaluate energy trends and provide recommendations",
    'Plant Operator' => "Vocational/Technical certificate or Bachelor's degree in Mechanical/Electrical Engineering\n\nKnowledge of plant equipment, operations, and safety standards\n\nTechnical skills in monitoring, troubleshooting, and maintenance\n\nAbility to operate and maintain continuous production processes",
    'Drilling Engineer' => "Bachelor's degree in Petroleum, Mechanical, or Chemical Engineering\n\nKnowledge of drilling techniques, rig operations, and safety regulations\n\nStrong technical, problem-solving, and project management skills\n\nAbility to plan and supervise drilling operations",
    'Maintenance Technician' => "Vocational/Technical certificate or Bachelor's in Mechanical/Electrical Engineering\n\nKnowledge of machinery maintenance, troubleshooting, and repair\n\nTechnical, safety, and problem-solving skills\n\nAbility to perform preventive maintenance and emergency repairs",
    'Field Operator' => "Vocational/Technical certificate or Bachelor's in Engineering or Oil & Gas operations\n\nKnowledge of production equipment, operations, and safety standards\n\nTechnical and physical skills for fieldwork\n\nAbility to monitor and maintain field production processes",
    'Pipeline Engineer' => "Bachelor's degree in Mechanical, Civil, or Petroleum Engineering\n\nKnowledge of pipeline design, construction, inspection, and maintenance\n\nStrong technical, analytical, and project management skills\n\nAbility to ensure pipeline safety, integrity, and efficiency",
    'Energy Consultant' => "Bachelor's degree in Energy Management, Engineering, or Environmental Science\n\nKnowledge of energy systems, optimization, and sustainability\n\nAnalytical, advisory, and project management skills\n\nAbility to provide recommendations for energy efficiency and cost reduction",
    'Refinery Technician' => "Vocational/Technical certificate or Bachelor's in Chemical/Petroleum Engineering\n\nKnowledge of refinery operations, equipment, and safety protocols\n\nTechnical, analytical, and problem-solving skills\n\nAbility to operate, monitor, and maintain refinery processes",
    'Production Engineer – Oil & Gas' => "Bachelor's degree in Petroleum, Chemical, or Mechanical Engineering\n\nKnowledge of oil & gas production processes, equipment, and safety standards\n\nStrong technical, analytical, and project management skills\n\nAbility to optimize production and troubleshoot operational issues",
    'Offshore Rig Technician' => "Vocational/Technical certificate or Bachelor's in Mechanical/Electrical Engineering\n\nKnowledge of offshore rig equipment, safety protocols, and operations\n\nTechnical, problem-solving, and physical skills\n\nAbility to perform maintenance and operations in offshore environments",
    // Chemical / Industrial Qualifications
    'Chemical Engineer' => "Bachelor's degree in Chemical Engineering or related field\n\nKnowledge of chemical processes, safety protocols, and production methods\n\nStrong analytical, problem-solving, and technical skills\n\nAbility to design, optimize, and supervise chemical operations",
    'Laboratory Technician' => "Vocational/Technical certificate or Bachelor's in Chemistry, Biochemistry, or related field\n\nKnowledge of lab procedures, equipment, and safety protocols\n\nAttention to detail, analytical, and organizational skills\n\nAbility to conduct experiments, analyze samples, and document results",
    'Process Operator' => "Vocational/Technical certificate or Bachelor's in Chemical/Industrial Engineering\n\nKnowledge of industrial processes, machinery, and safety standards\n\nTechnical, analytical, and problem-solving skills\n\nAbility to monitor, control, and maintain production processes",
    'Quality Analyst' => "Bachelor's degree in Chemistry, Chemical Engineering, or Quality Management\n\nKnowledge of quality control methods, lab analysis, and regulatory standards\n\nAnalytical, detail-oriented, and problem-solving skills\n\nAbility to perform product testing and ensure compliance",
    'Production Chemist' => "Bachelor's degree in Chemistry, Chemical Engineering, or related field\n\nKnowledge of chemical formulations, production processes, and safety standards\n\nAnalytical, technical, and troubleshooting skills\n\nAbility to develop, monitor, and optimize production",
    'Industrial Technician' => "Vocational/Technical certificate or Bachelor's in Industrial/Mechanical/Electrical Engineering\n\nKnowledge of industrial machinery, production systems, and safety protocols\n\nTechnical, problem-solving, and hands-on skills\n\nAbility to operate, maintain, and troubleshoot equipment",
    'Safety Officer' => "Bachelor's degree in Occupational Health & Safety, Chemical/Industrial Engineering\n\nKnowledge of chemical safety regulations, risk assessment, and industrial safety standards\n\nStrong observation, analytical, and communication skills\n\nAbility to enforce safety protocols, conduct inspections, and train staff",
    'Formulation Specialist' => "Bachelor's degree in Chemistry, Pharmaceutical Science, or Chemical Engineering\n\nKnowledge of product formulation, chemical properties, and lab techniques\n\nAnalytical, detail-oriented, and problem-solving skills\n\nAbility to develop, test, and optimize formulations",
    'Research Chemist' => "Bachelor's or Master's degree in Chemistry, Biochemistry, or related field\n\nKnowledge of chemical research methods, laboratory techniques, and safety protocols\n\nStrong analytical, experimental, and documentation skills\n\nAbility to conduct experiments, analyze data, and report findings",
    'Control Room Operator' => "Vocational/Technical certificate or Bachelor's in Chemical/Industrial/Electrical Engineering\n\nKnowledge of process control systems, instrumentation, and safety standards\n\nTechnical, monitoring, and problem-solving skills\n\nAbility to oversee plant operations and respond to alarms or emergencies",
    'Plant Chemist' => "Bachelor's degree in Chemistry, Chemical Engineering, or related field\n\nKnowledge of chemical production, quality control, and safety protocols\n\nAnalytical, technical, and problem-solving skills\n\nAbility to supervise production processes and ensure compliance",
    'Industrial Safety Officer' => "Bachelor's degree in Occupational Health & Safety, Chemical/Industrial Engineering\n\nKnowledge of industrial safety regulations, hazard assessment, and emergency response\n\nStrong analytical, observation, and communication skills\n\nAbility to implement and monitor safety programs in industrial facilities",
    // Allied Health / Special Education / Therapy Qualifications
    'Physical Therapist' => "Bachelor's or Doctorate in Physical Therapy\n\nKnowledge of human anatomy, rehabilitation techniques, and patient care\n\nStrong assessment, therapeutic, and communication skills\n\nAbility to design and implement treatment plans for patients",
    'Occupational Therapist' => "Bachelor's or Master's degree in Occupational Therapy\n\nKnowledge of rehabilitation, adaptive equipment, and daily living skills training\n\nAnalytical, patient-focused, and problem-solving skills\n\nAbility to assist patients in regaining functional independence",
    'Speech Therapist' => "Bachelor's or Master's degree in Speech-Language Pathology\n\nKnowledge of speech, language, and swallowing disorders\n\nStrong diagnostic, therapeutic, and communication skills\n\nAbility to design individualized treatment programs for patients",
    'Special Educator' => "Bachelor's degree in Special Education\n\nKnowledge of learning disabilities, individualized education plans (IEPs), and teaching strategies\n\nPatience, creativity, and strong instructional skills\n\nAbility to support students with special needs in academic and social development",
    'Rehabilitation Specialist' => "Bachelor's or Master's degree in Rehabilitation Science, Physical Therapy, or related field\n\nKnowledge of rehabilitation techniques and patient care\n\nStrong assessment, counseling, and therapeutic skills\n\nAbility to develop and monitor individualized rehabilitation programs",
    'Psychologist' => "Bachelor's and Master's degree in Psychology; licensure required\n\nKnowledge of mental health disorders, assessment, and therapy techniques\n\nStrong analytical, communication, and counseling skills\n\nAbility to provide therapy, assessment, and guidance to clients",
    'Audiologist' => "Bachelor's or Master's degree in Audiology\n\nKnowledge of hearing disorders, audiometric testing, and hearing aids\n\nStrong diagnostic, technical, and counseling skills\n\nAbility to assess, treat, and manage hearing and balance disorders",
    'Orthotist' => "Bachelor's degree in Orthotics and Prosthetics\n\nKnowledge of musculoskeletal disorders, orthotic device design, and fitting\n\nTechnical, analytical, and patient-care skills\n\nAbility to design, fabricate, and fit orthotic devices",
    'Prosthetist' => "Bachelor's degree in Prosthetics and Orthotics\n\nKnowledge of prosthetic design, fitting, and rehabilitation\n\nStrong technical, analytical, and patient-care skills\n\nAbility to create and fit prosthetic devices and provide follow-up care",
    'Behavioral Therapist' => "Bachelor's or Master's degree in Psychology, Behavioral Science, or related field\n\nKnowledge of behavior modification techniques and therapy programs\n\nPatience, observation, and counseling skills\n\nAbility to design and implement behavior intervention plans",
    'Therapy Assistant' => "Vocational/Technical certificate or Bachelor's in Allied Health or Rehabilitation\n\nKnowledge of therapeutic procedures and patient care support\n\nStrong communication and organizational skills\n\nAbility to assist therapists in implementing treatment plans",
    'Learning Support Officer' => "Bachelor's degree in Special Education, Psychology, or Education\n\nKnowledge of learning support strategies, educational tools, and student assessment\n\nStrong organizational, instructional, and communication skills\n\nAbility to support students with learning difficulties in academic progress",
    // Sports / Fitness / Recreation Qualifications
    'Fitness Trainer' => "Bachelor's degree in Physical Education, Kinesiology, or related field (or relevant certification)\n\nKnowledge of exercise physiology, nutrition, and fitness programs\n\nStrong motivational, instructional, and interpersonal skills\n\nAbility to design and lead personalized or group workout plans",
    'Personal Trainer' => "Bachelor's degree in Physical Education, Kinesiology, or related field (or relevant certification)\n\nKnowledge of exercise physiology, nutrition, and fitness programs\n\nStrong motivational, instructional, and interpersonal skills\n\nAbility to design and lead personalized or group workout plans",
    'Coach' => "Bachelor's degree in Physical Education, Sports Science, or related field\n\nKnowledge of sport-specific rules, techniques, and training methods\n\nLeadership, motivational, and communication skills\n\nAbility to train, guide, and mentor athletes for performance improvement",
    'Sports Analyst' => "Bachelor's degree in Sports Science, Statistics, or related field\n\nKnowledge of sports performance metrics, data analysis, and trends\n\nAnalytical, research, and reporting skills\n\nAbility to evaluate player/team performance and provide insights",
    'Recreation Coordinator' => "Bachelor's degree in Recreation, Leisure Studies, or related field\n\nKnowledge of recreation programs, event planning, and safety protocols\n\nOrganizational, communication, and interpersonal skills\n\nAbility to plan, implement, and oversee recreational activities",
    'Gym Instructor' => "Certification in fitness training, exercise science, or related field\n\nKnowledge of gym equipment, safety procedures, and training techniques\n\nInstructional, motivational, and interpersonal skills\n\nAbility to guide members in proper exercise techniques",
    'Yoga Instructor' => "Certification in Yoga teaching (RYT or equivalent)\n\nKnowledge of yoga postures, breathing techniques, and meditation\n\nStrong communication, instructional, and interpersonal skills\n\nAbility to teach individuals or groups of varying skill levels",
    'Athletic Trainer' => "Bachelor's or Master's degree in Athletic Training, Kinesiology, or related field\n\nKnowledge of injury prevention, rehabilitation, and sports medicine\n\nAssessment, therapeutic, and communication skills\n\nAbility to design and supervise injury-prevention and recovery programs",
    'Sports Official' => "Certification in sports officiating or related field\n\nKnowledge of rules, regulations, and standards of the sport\n\nDecision-making, communication, and observational skills\n\nAbility to enforce rules fairly and ensure safety during games",
    'Lifeguard' => "Certification in lifeguarding, CPR, and first aid\n\nKnowledge of water safety, rescue techniques, and emergency procedures\n\nPhysical fitness, vigilance, and communication skills\n\nAbility to monitor swimmers, prevent accidents, and respond to emergencies",
    'Wellness Coach' => "Bachelor's degree or certification in Health, Nutrition, or Wellness\n\nKnowledge of lifestyle management, mental health, and fitness programs\n\nMotivational, counseling, and planning skills\n\nAbility to guide clients toward healthier lifestyle choices",
    // Fashion / Apparel / Beauty Qualifications
    'Fashion Designer' => "Bachelor's degree in Fashion Design, Fine Arts, or related field\n\nKnowledge of garment construction, textiles, and fashion trends\n\nCreativity, drawing, and technical design skills\n\nAbility to create clothing designs and develop seasonal collections",
    'Stylist' => "Bachelor's degree in Fashion, Design, or related field (preferred)\n\nKnowledge of fashion trends, clothing coordination, and personal styling\n\nStrong interpersonal, communication, and creative skills\n\nAbility to advise clients on wardrobe and personal appearance",
    'Makeup Artist' => "Certification or diploma in Cosmetology or Makeup Artistry\n\nKnowledge of makeup techniques, skin types, and beauty products\n\nCreativity, precision, and interpersonal skills\n\nAbility to apply makeup for various occasions and photo/video shoots",
    'Boutique Manager' => "Bachelor's degree in Business, Retail Management, or related field\n\nKnowledge of retail operations, inventory management, and customer service\n\nLeadership, organizational, and sales skills\n\nAbility to manage staff, operations, and sales targets of the boutique",
    'Hairdresser' => "Certification or diploma in Cosmetology / Hairdressing\n\nKnowledge of hair cutting, styling, coloring, and treatments\n\nCreativity, precision, and customer service skills\n\nAbility to perform a variety of hair services professionally",
    'Fashion Merchandiser' => "Bachelor's degree in Fashion, Marketing, or Business\n\nKnowledge of fashion trends, retail buying, and visual merchandising\n\nAnalytical, organizational, and marketing skills\n\nAbility to plan product lines, promotions, and maximize sales",
    'Nail Technician' => "Certification or diploma in Cosmetology / Nail Technology\n\nKnowledge of nail care, manicures, pedicures, and nail art\n\nPrecision, creativity, and customer service skills\n\nAbility to provide professional nail services",
    'Costume Designer' => "Bachelor's degree in Fashion Design, Costume Design, or Fine Arts\n\nKnowledge of fabrics, historical garments, and costume construction\n\nCreativity, design, and sewing skills\n\nAbility to create costumes for theater, film, or performances",
    'Wardrobe Consultant' => "Bachelor's degree in Fashion, Design, or related field (preferred)\n\nKnowledge of fashion trends, body types, and clothing coordination\n\nInterpersonal, advisory, and styling skills\n\nAbility to advise clients on wardrobe selection and styling",
    'Beauty Therapist' => "Certification or diploma in Beauty Therapy / Cosmetology\n\nKnowledge of skincare, treatments, and cosmetic products\n\nInterpersonal, technical, and customer service skills\n\nAbility to perform facials, skin treatments, and relaxation therapies",
    'Fashion Illustrator' => "Bachelor's degree in Fashion Design, Illustration, or Fine Arts\n\nKnowledge of drawing techniques, fashion trends, and garment representation\n\nCreativity, attention to detail, and drawing skills\n\nAbility to create fashion sketches and illustrations for designs",
    'Image Consultant' => "Bachelor's degree in Fashion, Marketing, Communication, or related field\n\nKnowledge of personal styling, etiquette, and fashion trends\n\nInterpersonal, advisory, and presentation skills\n\nAbility to guide clients in professional and personal image enhancement",
    // Home / Personal Services Qualifications
    'Housekeeper' => "High school diploma or vocational training in housekeeping\n\nKnowledge of cleaning procedures, hygiene standards, and household management\n\nAttention to detail, reliability, and time-management skills\n\nAbility to maintain cleanliness and organization in residential spaces",
    'Nanny' => "High school diploma or degree in Early Childhood Education (preferred)\n\nKnowledge of child care, safety, and age-appropriate activities\n\nPatience, communication, and interpersonal skills\n\nAbility to provide care, supervision, and developmental support for children",
    'Caregiver' => "Certification or vocational training in caregiving / health care assistance\n\nKnowledge of elder care, first aid, and basic health monitoring\n\nCompassion, patience, and interpersonal skills\n\nAbility to assist with daily living activities, medication, and companionship",
    'Elderly Care Assistant' => "Certification or vocational training in caregiving / health care assistance\n\nKnowledge of elder care, first aid, and basic health monitoring\n\nCompassion, patience, and interpersonal skills\n\nAbility to assist with daily living activities, medication, and companionship",
    'Driver' => "High school diploma (minimum)\n\nValid driver's license appropriate for vehicle type\n\nKnowledge of traffic rules, navigation, and vehicle maintenance\n\nSafe driving record, reliability, and punctuality",
    'Gardener' => "Vocational training or diploma in horticulture, agriculture, or landscaping (preferred)\n\nKnowledge of plant care, gardening techniques, and landscape maintenance\n\nPhysical fitness, attention to detail, and reliability\n\nAbility to maintain lawns, gardens, and outdoor spaces",
    'Pet Groomer' => "Certification or vocational training in pet grooming or animal care\n\nKnowledge of pet grooming techniques, safety, and handling\n\nPatience, care, and attention to detail\n\nAbility to groom and care for pets professionally",
    'Laundry Attendant' => "High school diploma or vocational training (preferred)\n\nKnowledge of laundry equipment, cleaning agents, and fabric care\n\nAttention to detail, organization, and time-management skills\n\nAbility to wash, dry, fold, and maintain clothing properly",
    'Babysitter' => "High school diploma (preferred)\n\nKnowledge of child care, safety, and age-appropriate activities\n\nPatience, responsibility, and communication skills\n\nAbility to supervise, feed, and entertain children safely",
    'Home Care Aide' => "Certification in caregiving or health care assistance\n\nKnowledge of elder care, disability assistance, and first aid\n\nCompassion, patience, and reliability\n\nAbility to assist clients with daily activities and basic health needs",
    'Personal Assistant – Household' => "High school diploma or bachelor's degree in Business Administration or related field (preferred)\n\nKnowledge of household management, scheduling, and administrative support\n\nOrganizational, communication, and multitasking skills\n\nAbility to manage household tasks, appointments, and errands",
    // Insurance / Risk / Banking Qualifications
    'Insurance Agent' => "Bachelor's degree in Finance, Business Administration, or related field\n\nKnowledge of insurance products, policies, and regulations\n\nStrong communication, sales, and customer service skills\n\nAbility to assess client needs, sell policies, and provide advice",
    'Risk Analyst' => "Bachelor's degree in Finance, Economics, Business, or related field\n\nKnowledge of risk management, financial analysis, and regulatory compliance\n\nAnalytical, problem-solving, and decision-making skills\n\nAbility to identify, evaluate, and mitigate financial and operational risks",
    'Loan Officer' => "Bachelor's degree in Finance, Accounting, or related field\n\nKnowledge of lending procedures, credit evaluation, and banking regulations\n\nAnalytical, interpersonal, and customer service skills\n\nAbility to evaluate loan applications, assess creditworthiness, and approve loans",
    'Banking Teller' => "High school diploma or bachelor's degree in Finance/Accounting (preferred)\n\nKnowledge of banking procedures, cash handling, and customer service\n\nAccuracy, attention to detail, and communication skills\n\nAbility to process deposits, withdrawals, and transactions efficiently",
    'Claims Adjuster' => "Bachelor's degree in Insurance, Finance, Business, or related field\n\nKnowledge of insurance claims, assessment, and legal regulations\n\nAnalytical, investigative, and negotiation skills\n\nAbility to evaluate claims, determine coverage, and resolve disputes",
    'Underwriter' => "Bachelor's degree in Finance, Accounting, Business, or Insurance\n\nKnowledge of risk assessment, underwriting procedures, and financial analysis\n\nAnalytical, decision-making, and attention-to-detail skills\n\nAbility to assess applications, set terms, and approve coverage",
    'Financial Advisor' => "Bachelor's degree in Finance, Accounting, Economics, or related field\n\nKnowledge of investments, financial planning, and risk management\n\nAnalytical, interpersonal, and advisory skills\n\nAbility to guide clients on investments, retirement planning, and financial goals",
    'Credit Analyst' => "Bachelor's degree in Finance, Accounting, or Economics\n\nKnowledge of credit risk, financial statements, and lending policies\n\nAnalytical, research, and communication skills\n\nAbility to evaluate borrowers' creditworthiness and make recommendations",
    'Investment Officer' => "Bachelor's degree in Finance, Economics, Accounting, or related field\n\nKnowledge of investment products, portfolio management, and financial markets\n\nAnalytical, decision-making, and client advisory skills\n\nAbility to manage investment portfolios and provide financial recommendations",
    'Policy Consultant' => "Bachelor's degree in Finance, Business Administration, or related field\n\nKnowledge of insurance policies, regulations, and risk management\n\nCommunication, advisory, and analytical skills\n\nAbility to provide clients with policy guidance and risk mitigation strategies",
    'Branch Banking Officer' => "Bachelor's degree in Finance, Accounting, Business, or related field\n\nKnowledge of banking operations, products, and regulations\n\nLeadership, communication, and organizational skills\n\nAbility to manage branch operations, staff, and customer services",
    'Insurance Underwriting Assistant' => "Bachelor's degree in Finance, Accounting, Business, or related field\n\nKnowledge of insurance procedures, underwriting, and risk evaluation\n\nOrganizational, analytical, and communication skills\n\nAbility to assist underwriters in processing applications and risk assessment",
    // Micro Jobs / Informal / Daily Wage Jobs Qualifications
    'Delivery Rider' => "High school diploma (minimum)\n\nValid driver's license (motorcycle or bike)\n\nKnowledge of local roads and navigation\n\nPunctuality, reliability, and good customer service skills",
    'Vendor' => "No formal education required (high school preferred)\n\nKnowledge of products, basic sales, and customer interaction\n\nEntrepreneurial, organizational, and interpersonal skills\n\nAbility to manage sales, stock, and customers independently",
    'Market Seller' => "No formal education required (high school preferred)\n\nKnowledge of products, basic sales, and customer interaction\n\nEntrepreneurial, organizational, and interpersonal skills\n\nAbility to manage sales, stock, and customers independently",
    'Food Cart Vendor' => "No formal education required (high school preferred)\n\nKnowledge of products, basic sales, and customer interaction\n\nEntrepreneurial, organizational, and interpersonal skills\n\nAbility to manage sales, stock, and customers independently",
    'Street Cleaner' => "No formal education required\n\nPhysical fitness and endurance\n\nPunctuality, reliability, and willingness to work outdoors\n\nAbility to follow instructions and perform manual tasks efficiently",
    'Day Laborer' => "No formal education required\n\nPhysical fitness and endurance\n\nPunctuality, reliability, and willingness to work outdoors\n\nAbility to follow instructions and perform manual tasks efficiently",
    'Helper' => "No formal education required\n\nPhysical fitness and endurance\n\nPunctuality, reliability, and willingness to work outdoors\n\nAbility to follow instructions and perform manual tasks efficiently",
    'Errand Runner' => "No formal education required\n\nPhysical fitness and endurance\n\nPunctuality, reliability, and willingness to work outdoors\n\nAbility to follow instructions and perform manual tasks efficiently",
    'Construction Laborer' => "No formal education required (vocational training preferred)\n\nKnowledge of construction tools, safety procedures, and basic building techniques\n\nPhysical fitness, teamwork, and discipline\n\nAbility to perform manual labor tasks and assist skilled workers",
    'Messenger' => "High school diploma (preferred)\n\nKnowledge of local routes and communication protocols\n\nReliability, punctuality, and organizational skills\n\nAbility to deliver messages, documents, or parcels efficiently",
    'Driver' => "High school diploma (minimum)\n\nValid driver's license for vehicle type\n\nKnowledge of traffic rules, navigation, and vehicle maintenance\n\nSafe driving record, reliability, and punctuality",
    'Gig Worker' => "No formal education required (skills-based depending on gig)\n\nKnowledge and experience relevant to the gig (e.g., delivery, freelance tasks)\n\nTime management, adaptability, and reliability\n\nAbility to complete tasks efficiently and meet deadlines",
    // Real Estate / Property Qualifications
    'Real Estate Agent' => "Bachelor's degree in Real Estate, Business, Marketing, or related field (preferred)\n\nKnowledge of property market, sales processes, and legal requirements\n\nStrong communication, negotiation, and sales skills\n\nAbility to assist clients in buying, selling, or renting properties",
    'Property Manager' => "Bachelor's degree in Real Estate, Business Administration, or related field\n\nKnowledge of property management, tenancy laws, and maintenance procedures\n\nLeadership, organizational, and customer service skills\n\nAbility to manage rental properties, tenants, and maintenance operations",
    'Leasing Officer' => "Bachelor's degree in Real Estate, Business, or Marketing (preferred)\n\nKnowledge of leasing agreements, property management, and customer service\n\nCommunication, negotiation, and organizational skills\n\nAbility to manage lease contracts and assist tenants/clients",
    'Property Leasing Specialist' => "Bachelor's degree in Real Estate, Business, or Marketing (preferred)\n\nKnowledge of leasing agreements, property management, and customer service\n\nCommunication, negotiation, and organizational skills\n\nAbility to manage lease contracts and assist tenants/clients",
    'Appraiser' => "Bachelor's degree in Real Estate, Finance, or related field\n\nKnowledge of property valuation methods, market analysis, and legal regulations\n\nAnalytical, research, and reporting skills\n\nAbility to assess property values for sales, taxation, or investment",
    'Valuation Officer' => "Bachelor's degree in Real Estate, Finance, or related field\n\nKnowledge of property valuation methods, market analysis, and legal regulations\n\nAnalytical, research, and reporting skills\n\nAbility to assess property values for sales, taxation, or investment",
    'Broker' => "Bachelor's degree in Real Estate, Business, or Marketing (preferred)\n\nKnowledge of real estate laws, sales, and negotiation techniques\n\nStrong communication, leadership, and networking skills\n\nAbility to facilitate property transactions and oversee agents",
    'Real Estate Consultant' => "Bachelor's degree in Real Estate, Finance, Business, or related field\n\nKnowledge of property market trends, investment analysis, and regulations\n\nAnalytical, advisory, and communication skills\n\nAbility to guide clients in property investment and portfolio management",
    'Sales Executive' => "Bachelor's degree in Marketing, Business, or related field (preferred)\n\nKnowledge of sales strategies, property market, and client relations\n\nCommunication, persuasion, and negotiation skills\n\nAbility to achieve sales targets and maintain client relationships",
    'Development Manager' => "Bachelor's degree in Real Estate, Business, Project Management, or related field\n\nKnowledge of property development, construction processes, and finance\n\nLeadership, project management, and organizational skills\n\nAbility to oversee property development projects from planning to completion",
    'Estate Manager' => "Bachelor's degree in Real Estate, Property Management, or Business (preferred)\n\nKnowledge of estate operations, facilities management, and tenant relations\n\nLeadership, problem-solving, and organizational skills\n\nAbility to manage large properties, staff, and tenant needs",
    'Rental Officer' => "High school diploma or Bachelor's degree in Business or Real Estate (preferred)\n\nKnowledge of rental procedures, tenancy agreements, and client service\n\nOrganizational, communication, and negotiation skills\n\nAbility to manage rental contracts and maintain client relationships",
    // Entrepreneurship / Business / Corporate Qualifications
    'Chief Executive Officer' => "Bachelor's or Master's degree in Business Administration, Management, or related field\n\nExtensive experience in leadership, strategic planning, and corporate governance\n\nStrong decision-making, communication, and management skills\n\nAbility to lead the organization, make high-level decisions, and drive growth",
    'Startup Founder' => "Bachelor's degree (preferred) in Business, Entrepreneurship, or related field\n\nKnowledge of business development, marketing, and finance\n\nCreativity, problem-solving, and leadership skills\n\nAbility to conceptualize, launch, and grow a new business",
    'Business Analyst' => "Bachelor's degree in Business Administration, Finance, Economics, or IT\n\nKnowledge of business processes, data analysis, and market research\n\nAnalytical, problem-solving, and communication skills\n\nAbility to analyze operations, identify improvements, and provide actionable insights",
    'Operations Manager' => "Bachelor's degree in Business Administration, Management, or related field\n\nKnowledge of business operations, process improvement, and project management\n\nLeadership, organizational, and problem-solving skills\n\nAbility to oversee daily operations, optimize processes, and manage teams",
    'Project Manager' => "Bachelor's degree in Business, Management, Engineering, or related field\n\nKnowledge of project management methodologies, budgeting, and scheduling\n\nLeadership, planning, and communication skills\n\nAbility to plan, execute, and deliver projects on time and within budget",
    'Management Consultant' => "Bachelor's or Master's degree in Business Administration, Finance, or Management\n\nKnowledge of business strategy, operations, and performance improvement\n\nAnalytical, communication, and problem-solving skills\n\nAbility to advise organizations on improving efficiency, profitability, and growth",
    'Entrepreneur' => "No formal education required (Bachelor's preferred in Business or related field)\n\nKnowledge of business operations, market trends, and finance\n\nCreativity, risk-taking, and leadership skills\n\nAbility to identify opportunities, start, and grow a business",
    'Strategic Planner' => "Bachelor's degree in Business Administration, Management, or related field\n\nKnowledge of corporate strategy, market research, and business development\n\nAnalytical, planning, and decision-making skills\n\nAbility to develop long-term strategies to achieve organizational goals",
    'Corporate Officer' => "Bachelor's degree in Business, Finance, Management, or related field\n\nKnowledge of corporate governance, operations, and compliance\n\nLeadership, communication, and problem-solving skills\n\nAbility to manage corporate activities, ensure compliance, and support strategic initiatives",
    'Business Development Manager' => "Bachelor's degree in Business Administration, Marketing, or related field\n\nKnowledge of sales strategies, market analysis, and client relations\n\nNegotiation, communication, and analytical skills\n\nAbility to identify business opportunities, build partnerships, and drive growth",
    'Operations Analyst' => "Bachelor's degree in Business, Finance, Economics, or related field\n\nKnowledge of business processes, data analysis, and reporting\n\nAnalytical, problem-solving, and communication skills\n\nAbility to analyze operational data and provide recommendations for improvement",
    'Executive Director' => "Bachelor's or Master's degree in Business Administration, Management, or related field\n\nExtensive experience in leadership, strategic planning, and organizational management\n\nLeadership, communication, and decision-making skills\n\nAbility to oversee organizational operations and implement strategic initiatives"
];

$categorySkillOptionsJson = json_encode($categorySkillOptions, JSON_UNESCAPED_UNICODE);
$jobTitleToCategoryKeyJson = json_encode($jobTitleToCategoryKey, JSON_UNESCAPED_UNICODE);
$jobTitleToCategoryOptionJson = json_encode($jobTitleToCategoryOption, JSON_UNESCAPED_UNICODE);
$jobTitleDescriptionsJson = json_encode($jobTitleDescriptions, JSON_UNESCAPED_UNICODE);
$jobTitleToSkillsJson = json_encode($jobTitleToSkills, JSON_UNESCAPED_UNICODE);
$jobTitleToQualificationsJson = json_encode($jobTitleToQualifications, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Job - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --accent-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1f2937;
            --text-gray: #6b7280;
            --bg-light: #f9fafb;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body.employer-layout {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .employer-main-content {
            background: transparent;
            padding: 2rem;
        }

        /* Header Section */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .page-header h1 {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 2rem;
            margin: 0;
        }

        .btn-back {
            background: white;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }

        /* Card Styles */
        .dashboard-card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1.5rem;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .dashboard-card .card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
        }

        .dashboard-card .card-header h4,
        .dashboard-card .card-header h5 {
            margin: 0;
            color: white;
            font-weight: 700;
        }

        .dashboard-card .card-body {
            padding: 2.5rem;
        }

        .dashboard-card .card-body form {
            padding: 0;
        }

        /* Form Styles */
        .form-label {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }

        .form-label::before {
            content: '';
            width: 4px;
            height: 18px;
            background: var(--primary-gradient);
            border-radius: 2px;
            margin-right: 0.75rem;
        }

        .form-select,
        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-select-lg,
        .form-control-lg {
            padding: 1rem 1.5rem;
            font-size: 1.05rem;
        }

        textarea.form-control {
            border-radius: 12px;
            border: 2px solid var(--border-color);
            padding: 1rem 1.25rem;
            transition: all 0.3s ease;
            resize: vertical;
        }

        textarea.form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-text {
            color: var(--text-gray);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Button Styles */
        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-secondary-custom {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--text-dark);
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.3s ease;
        }

        .btn-secondary-custom:hover {
            background: var(--bg-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Tips Card */
        .tips-card {
            background: linear-gradient(135deg, #fff5e6 0%, #ffe8cc 100%);
            border-left: 4px solid var(--warning-color);
        }

        .tips-card .card-header {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }

        .tips-card ul li {
            padding: 0.875rem 0;
            border-bottom: 1px solid rgba(251, 191, 36, 0.2);
            display: flex;
            align-items: flex-start;
        }

        .tips-card ul li:last-child {
            border-bottom: none;
        }

        .tips-card ul li i {
            background: var(--warning-color);
            color: white;
            min-width: 28px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.875rem;
            margin-top: 0.125rem;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .guidelines-card ul li {
            padding: 0.75rem 0;
            display: flex;
            align-items: flex-start;
        }

        .guidelines-card ul li i {
            color: var(--success-color);
            margin-right: 0.875rem;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        /* Company Info Card */
        .company-card {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        }

        .company-card .card-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }

        .company-card img {
            border: 3px solid white;
            box-shadow: var(--shadow-md);
        }

        .btn-edit-profile {
            background: white;
            border: 2px solid #0ea5e9;
            color: #0ea5e9;
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-edit-profile:hover {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            transform: translateY(-2px);
        }

        /* Guidelines Card */
        .guidelines-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-left: 4px solid var(--success-color);
        }

        .guidelines-card .card-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .guidelines-card ul li {
            padding: 0.5rem 0;
            color: var(--text-dark);
        }

        .guidelines-card i {
            color: var(--success-color);
            font-size: 0.875rem;
        }

        /* Form Sections */
        .form-section {
            position: relative;
            padding: 1.75rem;
            background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .form-section:last-of-type {
            margin-bottom: 0;
        }

        .section-title {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem !important;
            display: flex;
            align-items: center;
        }

        .section-title i {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.5rem;
        }

        /* Section Dividers */
        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--border-color), transparent);
            margin: 2rem 0;
        }


        /* Top Cards Row Styling */
        .row.g-4.mb-4 .dashboard-card {
            height: 100%;
        }

        /* Ensure equal height cards in top row */
        @media (min-width: 992px) {
            .row.g-4.mb-4 {
                display: flex;
            }
            
            .row.g-4.mb-4 .col-lg-4 {
                display: flex;
            }
            
            .row.g-4.mb-4 .col-lg-4 .dashboard-card {
                flex: 1;
            }
        }

        /* Responsive */
        @media (max-width: 991px) {
            .row.g-4.mb-4 .col-lg-4 {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 768px) {
            .employer-main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .dashboard-card .card-body {
                padding: 1.5rem;
            }

            .form-section {
                padding: 1.25rem;
                margin-bottom: 1.5rem;
            }

            .section-title {
                font-size: 1.1rem;
                padding-bottom: 0.75rem;
                margin-bottom: 1rem !important;
            }

            .btn-primary-custom,
            .btn-secondary-custom {
                width: 100%;
                margin-bottom: 0.75rem;
            }
        }

        /* Input Group Enhancements */
        .input-group-custom {
            position: relative;
        }

        .input-group-custom .form-select,
        .input-group-custom .form-control {
            padding-left: 2.75rem;
        }

        .input-group-custom.no-input-icon .form-select,
        .input-group-custom.no-input-icon .form-control {
            padding-left: 1rem;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            z-index: 5;
            pointer-events: none;
            font-size: 1rem;
        }

        .input-group-custom select.form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23667eea' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        /* Required Field Indicator */
        .required-indicator {
            color: var(--danger-color);
            margin-left: 0.25rem;
        }
        
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-briefcase me-3"></i>Post New Job</h1>
                <p class="text-muted mb-0 mt-2">Create an attractive job posting to find the best candidates</p>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Information Cards at Top -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="card dashboard-card tips-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Job Posting Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Clear Title:</strong> Use specific, descriptive job titles
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Detailed Description:</strong> Include key responsibilities and company culture
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Specific Requirements:</strong> List must-have vs. nice-to-have skills
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Competitive Salary:</strong> Include salary range to attract quality candidates
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Deadline:</strong> Set a reasonable application deadline
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card dashboard-card company-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Company Information</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if ($company['company_logo']): ?>
                                <img src="../<?php echo $company['company_logo']; ?>" alt="Company Logo" class="img-fluid rounded-circle" style="max-height: 100px; width: 100px; object-fit: cover; border: 4px solid white; box-shadow: var(--shadow-md);">
                            <?php else: ?>
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white" style="width: 100px; height: 100px; box-shadow: var(--shadow-md);">
                                    <i class="fas fa-building fa-3x" style="color: #0ea5e9;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h6 class="fw-bold mb-2" style="color: var(--text-dark);"><?php echo htmlspecialchars($company['company_name']); ?></h6>
                        <p class="text-muted small mb-3">
                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($company['location_address']); ?>
                        </p>
                        <a href="company-profile.php" class="btn btn-edit-profile">
                            <i class="fas fa-edit me-1"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card dashboard-card guidelines-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Posting Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                All job posts are reviewed by admin
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                Jobs go live after approval
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                You'll receive email notifications
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                Edit jobs anytime after posting
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Information Form -->
        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>Job Information</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <!-- Basic Information Section -->
                            <div class="form-section mb-5">
                                <h5 class="section-title mb-4">
                                    <i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>
                                    Basic Information
                                </h5>
                                
                                <div class="mb-4">
                                    <label for="title" class="form-label fw-bold">
                                        Job Title<span class="required-indicator">*</span>
                                    </label>
                                    <div class="input-group-custom no-input-icon">
                                        <select class="form-select form-select-lg" name="title" id="title" required>
                                            <option value="">Select Job Title</option>
                                            <?php foreach ($jobTitleGroups as $groupLabel => $titles): ?>
                                                <?php foreach ($titles as $title): ?>
                                                    <option value="<?php echo htmlspecialchars($title); ?>" <?php echo ($_POST['title'] ?? '') === $title ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($title); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Courses Field (shown only for College Instructor) -->
                                <div class="mb-4" id="courses_field_container" style="display: none;">
                                    <label for="courses" class="form-label fw-bold">
                                        <i class="fas fa-book me-2" style="color: var(--primary-color);"></i>Course to Teach<span class="required-indicator">*</span>
                                    </label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-graduation-cap input-icon"></i>
                                        <select class="form-select form-select-lg" name="courses" id="courses">
                                            <option value="">Select Course</option>
                                            <option value="BS Accountancy" <?php echo ($_POST['courses'] ?? '') === 'BS Accountancy' ? 'selected' : ''; ?>>BS Accountancy</option>
                                            <option value="BS Accounting Information System" <?php echo ($_POST['courses'] ?? '') === 'BS Accounting Information System' ? 'selected' : ''; ?>>BS Accounting Information System</option>
                                            <option value="BS Aeronautical Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Aeronautical Engineering' ? 'selected' : ''; ?>>BS Aeronautical Engineering</option>
                                            <option value="BS Agribusiness" <?php echo ($_POST['courses'] ?? '') === 'BS Agribusiness' ? 'selected' : ''; ?>>BS Agribusiness</option>
                                            <option value="BS Agriculture" <?php echo ($_POST['courses'] ?? '') === 'BS Agriculture' ? 'selected' : ''; ?>>BS Agriculture</option>
                                            <option value="BS Agronomy" <?php echo ($_POST['courses'] ?? '') === 'BS Agronomy' ? 'selected' : ''; ?>>BS Agronomy</option>
                                            <option value="BS Aircraft Maintenance Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Aircraft Maintenance Technology' ? 'selected' : ''; ?>>BS Aircraft Maintenance Technology</option>
                                            <option value="BS Animal Science" <?php echo ($_POST['courses'] ?? '') === 'BS Animal Science' ? 'selected' : ''; ?>>BS Animal Science</option>
                                            <option value="BS Animation" <?php echo ($_POST['courses'] ?? '') === 'BS Animation' ? 'selected' : ''; ?>>BS Animation</option>
                                            <option value="BS Anthropology" <?php echo ($_POST['courses'] ?? '') === 'BS Anthropology' ? 'selected' : ''; ?>>BS Anthropology</option>
                                            <option value="BS Architecture" <?php echo ($_POST['courses'] ?? '') === 'BS Architecture' ? 'selected' : ''; ?>>BS Architecture</option>
                                            <option value="BS Automotive Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Automotive Engineering' ? 'selected' : ''; ?>>BS Automotive Engineering</option>
                                            <option value="BS Automotive Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Automotive Technology' ? 'selected' : ''; ?>>BS Automotive Technology</option>
                                            <option value="BA Broadcasting" <?php echo ($_POST['courses'] ?? '') === 'BA Broadcasting' ? 'selected' : ''; ?>>BA Broadcasting</option>
                                            <option value="BS Biology" <?php echo ($_POST['courses'] ?? '') === 'BS Biology' ? 'selected' : ''; ?>>BS Biology</option>
                                            <option value="BS Business Administration" <?php echo ($_POST['courses'] ?? '') === 'BS Business Administration' ? 'selected' : ''; ?>>BS Business Administration</option>
                                            <option value="BS Chemical Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Chemical Engineering' ? 'selected' : ''; ?>>BS Chemical Engineering</option>
                                            <option value="BS Chemistry" <?php echo ($_POST['courses'] ?? '') === 'BS Chemistry' ? 'selected' : ''; ?>>BS Chemistry</option>
                                            <option value="BS Civil Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Civil Engineering' ? 'selected' : ''; ?>>BS Civil Engineering</option>
                                            <option value="BS Communication" <?php echo ($_POST['courses'] ?? '') === 'BS Communication' ? 'selected' : ''; ?>>BS Communication</option>
                                            <option value="BS Computer Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Computer Engineering' ? 'selected' : ''; ?>>BS Computer Engineering</option>
                                            <option value="BS Computer Science" <?php echo ($_POST['courses'] ?? '') === 'BS Computer Science' ? 'selected' : ''; ?>>BS Computer Science</option>
                                            <option value="BS Correctional Administration" <?php echo ($_POST['courses'] ?? '') === 'BS Correctional Administration' ? 'selected' : ''; ?>>BS Correctional Administration</option>
                                            <option value="BS Criminology" <?php echo ($_POST['courses'] ?? '') === 'BS Criminology' ? 'selected' : ''; ?>>BS Criminology</option>
                                            <option value="BS Culinary Arts" <?php echo ($_POST['courses'] ?? '') === 'BS Culinary Arts' ? 'selected' : ''; ?>>BS Culinary Arts</option>
                                            <option value="BS Customs Administration" <?php echo ($_POST['courses'] ?? '') === 'BS Customs Administration' ? 'selected' : ''; ?>>BS Customs Administration</option>
                                            <option value="BS Cybersecurity" <?php echo ($_POST['courses'] ?? '') === 'BS Cybersecurity' ? 'selected' : ''; ?>>BS Cybersecurity</option>
                                            <option value="BS Data Science" <?php echo ($_POST['courses'] ?? '') === 'BS Data Science' ? 'selected' : ''; ?>>BS Data Science</option>
                                            <option value="Doctor of Dental Medicine" <?php echo ($_POST['courses'] ?? '') === 'Doctor of Dental Medicine' ? 'selected' : ''; ?>>Doctor of Dental Medicine</option>
                                            <option value="BS Development Studies" <?php echo ($_POST['courses'] ?? '') === 'BS Development Studies' ? 'selected' : ''; ?>>BS Development Studies</option>
                                            <option value="Doctor of Medicine" <?php echo ($_POST['courses'] ?? '') === 'Doctor of Medicine' ? 'selected' : ''; ?>>Doctor of Medicine</option>
                                            <option value="BS Economics" <?php echo ($_POST['courses'] ?? '') === 'BS Economics' ? 'selected' : ''; ?>>BS Economics</option>
                                            <option value="Bachelor of Early Childhood Education" <?php echo ($_POST['courses'] ?? '') === 'Bachelor of Early Childhood Education' ? 'selected' : ''; ?>>Bachelor of Early Childhood Education</option>
                                            <option value="BS Electrical Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Electrical Engineering' ? 'selected' : ''; ?>>BS Electrical Engineering</option>
                                            <option value="BS Electrical Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Electrical Technology' ? 'selected' : ''; ?>>BS Electrical Technology</option>
                                            <option value="BS Electronics Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Electronics Engineering' ? 'selected' : ''; ?>>BS Electronics Engineering</option>
                                            <option value="BS Electronics Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Electronics Technology' ? 'selected' : ''; ?>>BS Electronics Technology</option>
                                            <option value="BS Elementary Education" <?php echo ($_POST['courses'] ?? '') === 'BS Elementary Education' ? 'selected' : ''; ?>>BS Elementary Education</option>
                                            <option value="BS Environmental Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Environmental Engineering' ? 'selected' : ''; ?>>BS Environmental Engineering</option>
                                            <option value="BS Environmental Science" <?php echo ($_POST['courses'] ?? '') === 'BS Environmental Science' ? 'selected' : ''; ?>>BS Environmental Science</option>
                                            <option value="BS Exercise and Sports Science" <?php echo ($_POST['courses'] ?? '') === 'BS Exercise and Sports Science' ? 'selected' : ''; ?>>BS Exercise and Sports Science</option>
                                            <option value="BS Fisheries" <?php echo ($_POST['courses'] ?? '') === 'BS Fisheries' ? 'selected' : ''; ?>>BS Fisheries</option>
                                            <option value="BA Film" <?php echo ($_POST['courses'] ?? '') === 'BA Film' ? 'selected' : ''; ?>>BA Film</option>
                                            <option value="Bachelor of Fine Arts" <?php echo ($_POST['courses'] ?? '') === 'Bachelor of Fine Arts' ? 'selected' : ''; ?>>Bachelor of Fine Arts</option>
                                            <option value="BS Forestry" <?php echo ($_POST['courses'] ?? '') === 'BS Forestry' ? 'selected' : ''; ?>>BS Forestry</option>
                                            <option value="BS Forensic Science" <?php echo ($_POST['courses'] ?? '') === 'BS Forensic Science' ? 'selected' : ''; ?>>BS Forensic Science</option>
                                            <option value="BS Game Art and Design" <?php echo ($_POST['courses'] ?? '') === 'BS Game Art and Design' ? 'selected' : ''; ?>>BS Game Art and Design</option>
                                            <option value="BS Game Development" <?php echo ($_POST['courses'] ?? '') === 'BS Game Development' ? 'selected' : ''; ?>>BS Game Development</option>
                                            <option value="BS Geodetic Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Geodetic Engineering' ? 'selected' : ''; ?>>BS Geodetic Engineering</option>
                                            <option value="BS Hospitality Management" <?php echo ($_POST['courses'] ?? '') === 'BS Hospitality Management' ? 'selected' : ''; ?>>BS Hospitality Management</option>
                                            <option value="BA History" <?php echo ($_POST['courses'] ?? '') === 'BA History' ? 'selected' : ''; ?>>BA History</option>
                                            <option value="BS Hotel and Restaurant Management" <?php echo ($_POST['courses'] ?? '') === 'BS Hotel and Restaurant Management' ? 'selected' : ''; ?>>BS Hotel and Restaurant Management</option>
                                            <option value="BS Human Resource Management" <?php echo ($_POST['courses'] ?? '') === 'BS Human Resource Management' ? 'selected' : ''; ?>>BS Human Resource Management</option>
                                            <option value="BS Industrial Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Industrial Engineering' ? 'selected' : ''; ?>>BS Industrial Engineering</option>
                                            <option value="BS Industrial Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Industrial Technology' ? 'selected' : ''; ?>>BS Industrial Technology</option>
                                            <option value="BS Information Systems" <?php echo ($_POST['courses'] ?? '') === 'BS Information Systems' ? 'selected' : ''; ?>>BS Information Systems</option>
                                            <option value="BS Information Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Information Technology' ? 'selected' : ''; ?>>BS Information Technology</option>
                                            <option value="BS Interior Design" <?php echo ($_POST['courses'] ?? '') === 'BS Interior Design' ? 'selected' : ''; ?>>BS Interior Design</option>
                                            <option value="BS International Business" <?php echo ($_POST['courses'] ?? '') === 'BS International Business' ? 'selected' : ''; ?>>BS International Business</option>
                                            <option value="BS International Studies" <?php echo ($_POST['courses'] ?? '') === 'BS International Studies' ? 'selected' : ''; ?>>BS International Studies</option>
                                            <option value="Juris Doctor" <?php echo ($_POST['courses'] ?? '') === 'Juris Doctor' ? 'selected' : ''; ?>>Juris Doctor</option>
                                            <option value="BA Journalism" <?php echo ($_POST['courses'] ?? '') === 'BA Journalism' ? 'selected' : ''; ?>>BA Journalism</option>
                                            <option value="BS Landscape Architecture" <?php echo ($_POST['courses'] ?? '') === 'BS Landscape Architecture' ? 'selected' : ''; ?>>BS Landscape Architecture</option>
                                            <option value="BA Mass Communication" <?php echo ($_POST['courses'] ?? '') === 'BA Mass Communication' ? 'selected' : ''; ?>>BA Mass Communication</option>
                                            <option value="BS Management Accounting" <?php echo ($_POST['courses'] ?? '') === 'BS Management Accounting' ? 'selected' : ''; ?>>BS Management Accounting</option>
                                            <option value="BS Marine Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Marine Engineering' ? 'selected' : ''; ?>>BS Marine Engineering</option>
                                            <option value="BS Marine Transportation" <?php echo ($_POST['courses'] ?? '') === 'BS Marine Transportation' ? 'selected' : ''; ?>>BS Marine Transportation</option>
                                            <option value="BS Marketing Management" <?php echo ($_POST['courses'] ?? '') === 'BS Marketing Management' ? 'selected' : ''; ?>>BS Marketing Management</option>
                                            <option value="BS Materials Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Materials Engineering' ? 'selected' : ''; ?>>BS Materials Engineering</option>
                                            <option value="BS Mathematics" <?php echo ($_POST['courses'] ?? '') === 'BS Mathematics' ? 'selected' : ''; ?>>BS Mathematics</option>
                                            <option value="BS Mechanical Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Mechanical Engineering' ? 'selected' : ''; ?>>BS Mechanical Engineering</option>
                                            <option value="BS Mechanical Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Mechanical Technology' ? 'selected' : ''; ?>>BS Mechanical Technology</option>
                                            <option value="BS Mechatronics Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Mechatronics Engineering' ? 'selected' : ''; ?>>BS Mechatronics Engineering</option>
                                            <option value="BS Mechatronics Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Mechatronics Technology' ? 'selected' : ''; ?>>BS Mechatronics Technology</option>
                                            <option value="BS Medical Laboratory Science" <?php echo ($_POST['courses'] ?? '') === 'BS Medical Laboratory Science' ? 'selected' : ''; ?>>BS Medical Laboratory Science</option>
                                            <option value="BS Medical Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Medical Technology' ? 'selected' : ''; ?>>BS Medical Technology</option>
                                            <option value="BS Meteorology" <?php echo ($_POST['courses'] ?? '') === 'BS Meteorology' ? 'selected' : ''; ?>>BS Meteorology</option>
                                            <option value="BS Midwifery" <?php echo ($_POST['courses'] ?? '') === 'BS Midwifery' ? 'selected' : ''; ?>>BS Midwifery</option>
                                            <option value="BS Mining Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Mining Engineering' ? 'selected' : ''; ?>>BS Mining Engineering</option>
                                            <option value="BS Multimedia Arts" <?php echo ($_POST['courses'] ?? '') === 'BS Multimedia Arts' ? 'selected' : ''; ?>>BS Multimedia Arts</option>
                                            <option value="BS Naval Architecture" <?php echo ($_POST['courses'] ?? '') === 'BS Naval Architecture' ? 'selected' : ''; ?>>BS Naval Architecture</option>
                                            <option value="BS Nursing" <?php echo ($_POST['courses'] ?? '') === 'BS Nursing' ? 'selected' : ''; ?>>BS Nursing</option>
                                            <option value="BS Nutrition and Dietetics" <?php echo ($_POST['courses'] ?? '') === 'BS Nutrition and Dietetics' ? 'selected' : ''; ?>>BS Nutrition and Dietetics</option>
                                            <option value="BS Office Administration" <?php echo ($_POST['courses'] ?? '') === 'BS Office Administration' ? 'selected' : ''; ?>>BS Office Administration</option>
                                            <option value="BS Operations Management" <?php echo ($_POST['courses'] ?? '') === 'BS Operations Management' ? 'selected' : ''; ?>>BS Operations Management</option>
                                            <option value="BS Optometry" <?php echo ($_POST['courses'] ?? '') === 'BS Optometry' ? 'selected' : ''; ?>>BS Optometry</option>
                                            <option value="BS Occupational Therapy" <?php echo ($_POST['courses'] ?? '') === 'BS Occupational Therapy' ? 'selected' : ''; ?>>BS Occupational Therapy</option>
                                            <option value="BS Pharmacy" <?php echo ($_POST['courses'] ?? '') === 'BS Pharmacy' ? 'selected' : ''; ?>>BS Pharmacy</option>
                                            <option value="BS Petroleum Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Petroleum Engineering' ? 'selected' : ''; ?>>BS Petroleum Engineering</option>
                                            <option value="BS Philosophy" <?php echo ($_POST['courses'] ?? '') === 'BS Philosophy' ? 'selected' : ''; ?>>BS Philosophy</option>
                                            <option value="BS Physical Education" <?php echo ($_POST['courses'] ?? '') === 'BS Physical Education' ? 'selected' : ''; ?>>BS Physical Education</option>
                                            <option value="BS Physical Therapy" <?php echo ($_POST['courses'] ?? '') === 'BS Physical Therapy' ? 'selected' : ''; ?>>BS Physical Therapy</option>
                                            <option value="BS Physics" <?php echo ($_POST['courses'] ?? '') === 'BS Physics' ? 'selected' : ''; ?>>BS Physics</option>
                                            <option value="BS Political Science" <?php echo ($_POST['courses'] ?? '') === 'BS Political Science' ? 'selected' : ''; ?>>BS Political Science</option>
                                            <option value="BS Psychology" <?php echo ($_POST['courses'] ?? '') === 'BS Psychology' ? 'selected' : ''; ?>>BS Psychology</option>
                                            <option value="BS Public Administration" <?php echo ($_POST['courses'] ?? '') === 'BS Public Administration' ? 'selected' : ''; ?>>BS Public Administration</option>
                                            <option value="BS Public Health" <?php echo ($_POST['courses'] ?? '') === 'BS Public Health' ? 'selected' : ''; ?>>BS Public Health</option>
                                            <option value="BS Public Safety Administration" <?php echo ($_POST['courses'] ?? '') === 'BS Public Safety Administration' ? 'selected' : ''; ?>>BS Public Safety Administration</option>
                                            <option value="BS Radiologic Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Radiologic Technology' ? 'selected' : ''; ?>>BS Radiologic Technology</option>
                                            <option value="BS Real Estate Management" <?php echo ($_POST['courses'] ?? '') === 'BS Real Estate Management' ? 'selected' : ''; ?>>BS Real Estate Management</option>
                                            <option value="BS Refrigeration and Air Conditioning Technology" <?php echo ($_POST['courses'] ?? '') === 'BS Refrigeration and Air Conditioning Technology' ? 'selected' : ''; ?>>BS Refrigeration and Air Conditioning Technology</option>
                                            <option value="BS Religious Education" <?php echo ($_POST['courses'] ?? '') === 'BS Religious Education' ? 'selected' : ''; ?>>BS Religious Education</option>
                                            <option value="BS Respiratory Therapy" <?php echo ($_POST['courses'] ?? '') === 'BS Respiratory Therapy' ? 'selected' : ''; ?>>BS Respiratory Therapy</option>
                                            <option value="BS Secondary Education" <?php echo ($_POST['courses'] ?? '') === 'BS Secondary Education' ? 'selected' : ''; ?>>BS Secondary Education</option>
                                            <option value="BS Software Engineering" <?php echo ($_POST['courses'] ?? '') === 'BS Software Engineering' ? 'selected' : ''; ?>>BS Software Engineering</option>
                                            <option value="BS Sociology" <?php echo ($_POST['courses'] ?? '') === 'BS Sociology' ? 'selected' : ''; ?>>BS Sociology</option>
                                            <option value="BS Special Needs Education" <?php echo ($_POST['courses'] ?? '') === 'BS Special Needs Education' ? 'selected' : ''; ?>>BS Special Needs Education</option>
                                            <option value="BS Sports Science" <?php echo ($_POST['courses'] ?? '') === 'BS Sports Science' ? 'selected' : ''; ?>>BS Sports Science</option>
                                            <option value="BS Statistics" <?php echo ($_POST['courses'] ?? '') === 'BS Statistics' ? 'selected' : ''; ?>>BS Statistics</option>
                                            <option value="BS Technology and Livelihood Education" <?php echo ($_POST['courses'] ?? '') === 'BS Technology and Livelihood Education' ? 'selected' : ''; ?>>BS Technology and Livelihood Education</option>
                                            <option value="BS Theater Arts" <?php echo ($_POST['courses'] ?? '') === 'BS Theater Arts' ? 'selected' : ''; ?>>BS Theater Arts</option>
                                            <option value="BS Theology" <?php echo ($_POST['courses'] ?? '') === 'BS Theology' ? 'selected' : ''; ?>>BS Theology</option>
                                            <option value="BS Tourism Management" <?php echo ($_POST['courses'] ?? '') === 'BS Tourism Management' ? 'selected' : ''; ?>>BS Tourism Management</option>
                                            <option value="BS Travel Management" <?php echo ($_POST['courses'] ?? '') === 'BS Travel Management' ? 'selected' : ''; ?>>BS Travel Management</option>
                                            <option value="BS Urban and Regional Planning" <?php echo ($_POST['courses'] ?? '') === 'BS Urban and Regional Planning' ? 'selected' : ''; ?>>BS Urban and Regional Planning</option>
                                            <option value="BS Values Education" <?php echo ($_POST['courses'] ?? '') === 'BS Values Education' ? 'selected' : ''; ?>>BS Values Education</option>
                                        </select>
                                    </div>
                                    <div class="form-text"><i class="fas fa-info-circle me-1"></i>Select the course that the College Instructor will teach</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="category_id" class="form-label fw-bold">
                                            <i class="fas fa-tags me-2" style="color: var(--primary-color);"></i>Category
                                        </label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-folder input-icon"></i>
                                            <select class="form-select form-select-lg" name="category_id" id="category_id">
                                                <option value="">Select Category</option>
                                                <?php if ($filteredByPosition && $filteredCategoryValue !== null): ?>
                                                <option value="<?php echo htmlspecialchars($filteredCategoryValue); ?>" data-category-name="<?php echo htmlspecialchars($filteredCategoryValue); ?>" selected><?php echo htmlspecialchars($filteredCategoryDisplay); ?></option>
                                                <?php else: ?>
                                        <option value="Administrative / Office" data-category-name="Administrative / Office" <?php echo ($_POST['category_id'] ?? '') === 'Administrative / Office' ? 'selected' : ''; ?>>🗂️ Administrative / Office</option>
                                        <option value="Customer Service / BPO" data-category-name="Customer Service / BPO" <?php echo ($_POST['category_id'] ?? '') === 'Customer Service / BPO' ? 'selected' : ''; ?>>☎️ Customer Service / BPO</option>
                                        <option value="Education" data-category-name="Education" <?php echo ($_POST['category_id'] ?? '') === 'Education' ? 'selected' : ''; ?>>🎓 Education</option>
                                        <option value="Engineering" data-category-name="Engineering" <?php echo ($_POST['category_id'] ?? '') === 'Engineering' ? 'selected' : ''; ?>>⚙️ Engineering</option>
                                        <option value="Information Technology (IT)" data-category-name="Information Technology (IT)" <?php echo ($_POST['category_id'] ?? '') === 'Information Technology (IT)' ? 'selected' : ''; ?>>💻 Information Technology (IT)</option>
                                        <option value="Finance / Accounting" data-category-name="Finance / Accounting" <?php echo ($_POST['category_id'] ?? '') === 'Finance / Accounting' ? 'selected' : ''; ?>>💰 Finance / Accounting</option>
                                        <option value="Healthcare / Medical" data-category-name="Healthcare / Medical" <?php echo ($_POST['category_id'] ?? '') === 'Healthcare / Medical' ? 'selected' : ''; ?>>🏥 Healthcare / Medical</option>
                                        <option value="Human Resources (HR)" data-category-name="Human Resources (HR)" <?php echo ($_POST['category_id'] ?? '') === 'Human Resources (HR)' ? 'selected' : ''; ?>>👥 Human Resources (HR)</option>
                                        <option value="Manufacturing / Production" data-category-name="Manufacturing / Production" <?php echo ($_POST['category_id'] ?? '') === 'Manufacturing / Production' ? 'selected' : ''; ?>>🏭 Manufacturing / Production</option>
                                        <option value="Logistics / Warehouse / Supply Chain" data-category-name="Logistics / Warehouse / Supply Chain" <?php echo ($_POST['category_id'] ?? '') === 'Logistics / Warehouse / Supply Chain' ? 'selected' : ''; ?>>🚚 Logistics / Warehouse / Supply Chain</option>
                                        <option value="Marketing / Sales" data-category-name="Marketing / Sales" <?php echo ($_POST['category_id'] ?? '') === 'Marketing / Sales' ? 'selected' : ''; ?>>📈 Marketing / Sales</option>
                                        <option value="Creative / Media / Design" data-category-name="Creative / Media / Design" <?php echo ($_POST['category_id'] ?? '') === 'Creative / Media / Design' ? 'selected' : ''; ?>>🎨 Creative / Media / Design</option>
                                        <option value="Construction / Infrastructure" data-category-name="Construction / Infrastructure" <?php echo ($_POST['category_id'] ?? '') === 'Construction / Infrastructure' ? 'selected' : ''; ?>>🏗️ Construction / Infrastructure</option>
                                        <option value="Food / Hospitality / Tourism (including Fast-Food Chains)" data-category-name="Food / Hospitality / Tourism (including Fast-Food Chains)" <?php echo ($_POST['category_id'] ?? '') === 'Food / Hospitality / Tourism (including Fast-Food Chains)' ? 'selected' : ''; ?>>🍽️ Food / Hospitality / Tourism (including Fast-Food Chains)</option>
                                        <option value="Retail / Sales Operations" data-category-name="Retail / Sales Operations" <?php echo ($_POST['category_id'] ?? '') === 'Retail / Sales Operations' ? 'selected' : ''; ?>>🛒 Retail / Sales Operations</option>
                                        <option value="Transportation" data-category-name="Transportation" <?php echo ($_POST['category_id'] ?? '') === 'Transportation' ? 'selected' : ''; ?>>🚗 Transportation</option>
                                        <option value="Law Enforcement / Criminology" data-category-name="Law Enforcement / Criminology" <?php echo ($_POST['category_id'] ?? '') === 'Law Enforcement / Criminology' ? 'selected' : ''; ?>>👮 Law Enforcement / Criminology</option>
                                        <option value="Security Services" data-category-name="Security Services" <?php echo ($_POST['category_id'] ?? '') === 'Security Services' ? 'selected' : ''; ?>>🛡️ Security Services</option>
                                        <option value="Skilled / Technical (TESDA)" data-category-name="Skilled / Technical (TESDA)" <?php echo ($_POST['category_id'] ?? '') === 'Skilled / Technical (TESDA)' ? 'selected' : ''; ?>>🔧 Skilled / Technical (TESDA)</option>
                                        <option value="Agriculture / Fisheries" data-category-name="Agriculture / Fisheries" <?php echo ($_POST['category_id'] ?? '') === 'Agriculture / Fisheries' ? 'selected' : ''; ?>>🌾 Agriculture / Fisheries</option>
                                        <option value="Freelance / Online / Remote" data-category-name="Freelance / Online / Remote" <?php echo ($_POST['category_id'] ?? '') === 'Freelance / Online / Remote' ? 'selected' : ''; ?>>🌐 Freelance / Online / Remote</option>
                                        <option value="Legal / Government / Public Service" data-category-name="Legal / Government / Public Service" <?php echo ($_POST['category_id'] ?? '') === 'Legal / Government / Public Service' ? 'selected' : ''; ?>>⚖️ Legal / Government / Public Service</option>
                                        <option value="Maritime / Aviation / Transport Specialized" data-category-name="Maritime / Aviation / Transport Specialized" <?php echo ($_POST['category_id'] ?? '') === 'Maritime / Aviation / Transport Specialized' ? 'selected' : ''; ?>>✈️ Maritime / Aviation / Transport Specialized</option>
                                        <option value="Science / Research / Environment" data-category-name="Science / Research / Environment" <?php echo ($_POST['category_id'] ?? '') === 'Science / Research / Environment' ? 'selected' : ''; ?>>🔬 Science / Research / Environment</option>
                                        <option value="Arts / Entertainment / Culture" data-category-name="Arts / Entertainment / Culture" <?php echo ($_POST['category_id'] ?? '') === 'Arts / Entertainment / Culture' ? 'selected' : ''; ?>>🎭 Arts / Entertainment / Culture</option>
                                        <option value="Religion / NGO / Development / Cooperative" data-category-name="Religion / NGO / Development / Cooperative" <?php echo ($_POST['category_id'] ?? '') === 'Religion / NGO / Development / Cooperative' ? 'selected' : ''; ?>>✝️ Religion / NGO / Development / Cooperative</option>
                                        <option value="Special / Rare Jobs" data-category-name="Special / Rare Jobs" <?php echo ($_POST['category_id'] ?? '') === 'Special / Rare Jobs' ? 'selected' : ''; ?>>🧩 Special / Rare Jobs</option>
                                        <option value="Utilities / Public Services" data-category-name="Utilities / Public Services" <?php echo ($_POST['category_id'] ?? '') === 'Utilities / Public Services' ? 'selected' : ''; ?>>🔌 Utilities / Public Services</option>
                                        <option value="Telecommunications" data-category-name="Telecommunications" <?php echo ($_POST['category_id'] ?? '') === 'Telecommunications' ? 'selected' : ''; ?>>📡 Telecommunications</option>
                                        <option value="Mining / Geology" data-category-name="Mining / Geology" <?php echo ($_POST['category_id'] ?? '') === 'Mining / Geology' ? 'selected' : ''; ?>>⛏️ Mining / Geology</option>
                                        <option value="Oil / Gas / Energy" data-category-name="Oil / Gas / Energy" <?php echo ($_POST['category_id'] ?? '') === 'Oil / Gas / Energy' ? 'selected' : ''; ?>>🛢️ Oil / Gas / Energy</option>
                                        <option value="Chemical / Industrial" data-category-name="Chemical / Industrial" <?php echo ($_POST['category_id'] ?? '') === 'Chemical / Industrial' ? 'selected' : ''; ?>>⚗️ Chemical / Industrial</option>
                                        <option value="Allied Health / Special Education / Therapy" data-category-name="Allied Health / Special Education / Therapy" <?php echo ($_POST['category_id'] ?? '') === 'Allied Health / Special Education / Therapy' ? 'selected' : ''; ?>>🩺 Allied Health / Special Education / Therapy</option>
                                        <option value="Sports / Fitness / Recreation" data-category-name="Sports / Fitness / Recreation" <?php echo ($_POST['category_id'] ?? '') === 'Sports / Fitness / Recreation' ? 'selected' : ''; ?>>🏋️ Sports / Fitness / Recreation</option>
                                        <option value="Fashion / Apparel / Beauty" data-category-name="Fashion / Apparel / Beauty" <?php echo ($_POST['category_id'] ?? '') === 'Fashion / Apparel / Beauty' ? 'selected' : ''; ?>>👗 Fashion / Apparel / Beauty</option>
                                        <option value="Home / Personal Services" data-category-name="Home / Personal Services" <?php echo ($_POST['category_id'] ?? '') === 'Home / Personal Services' ? 'selected' : ''; ?>>🏡 Home / Personal Services</option>
                                        <option value="Insurance / Risk / Banking" data-category-name="Insurance / Risk / Banking" <?php echo ($_POST['category_id'] ?? '') === 'Insurance / Risk / Banking' ? 'selected' : ''; ?>>🏦 Insurance / Risk / Banking</option>
                                        <option value="Micro Jobs / Informal / Daily Wage Jobs" data-category-name="Micro Jobs / Informal / Daily Wage Jobs" <?php echo ($_POST['category_id'] ?? '') === 'Micro Jobs / Informal / Daily Wage Jobs' ? 'selected' : ''; ?>>💼 Micro Jobs / Informal / Daily Wage Jobs</option>
                                        <option value="Real Estate / Property" data-category-name="Real Estate / Property" <?php echo ($_POST['category_id'] ?? '') === 'Real Estate / Property' ? 'selected' : ''; ?>>🏠 Real Estate / Property</option>
                                        <option value="Entrepreneurship / Business / Corporate" data-category-name="Entrepreneurship / Business / Corporate" <?php echo ($_POST['category_id'] ?? '') === 'Entrepreneurship / Business / Corporate' ? 'selected' : ''; ?>>📊 Entrepreneurship / Business / Corporate</option>
                                                <?php endif; ?>
                                    </select>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-map-marker-alt me-2" style="color: var(--primary-color);"></i>Branch Location<span class="required-indicator">*</span>
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="input-group-custom">
                                                <i class="fas fa-city input-icon"></i>
                                                <select class="form-select form-select-lg" name="job_city" id="job_city" autocomplete="address-level2" data-selected="<?php echo htmlspecialchars($jobCityCode); ?>" data-location-value="<?php echo htmlspecialchars($locationValue); ?>" required>
                                                    <option value="">Select City/Municipality</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group-custom">
                                                <i class="fas fa-map-pin input-icon"></i>
                                                <select class="form-select form-select-lg" name="job_barangay" id="job_barangay" autocomplete="address-level3" disabled data-selected="<?php echo htmlspecialchars($jobBarangayCode); ?>" required>
                                                    <option value="">Select Barangay</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="location" id="job_location" value="<?php echo htmlspecialchars($locationValue); ?>" required>
                                    <div class="form-text"><i class="fas fa-info-circle me-1"></i>Select city/municipality and barangay (Philippines only)</div>
                                </div>
                            </div>

                            <!-- Job Details Section -->
                            <div class="form-section mb-5">
                                <h5 class="section-title mb-4">
                                    <i class="fas fa-clipboard-list me-2" style="color: var(--primary-color);"></i>
                                    Job Details
                                </h5>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="experience_level" class="form-label fw-bold">
                                        <i class="fas fa-chart-line me-2" style="color: var(--primary-color);"></i>Experience Required<span class="required-indicator">*</span>
                                    </label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-user-tie input-icon"></i>
                                        <select class="form-select form-select-lg" name="experience_level" required>
                                            <option value="">Select Experience Required</option>
                                            <option value="0 to 1 year" <?php echo ($_POST['experience_level'] ?? '') === '0 to 1 year' ? 'selected' : ''; ?>>No Experience</option>
                                            <option value="1 to 2 years" <?php echo ($_POST['experience_level'] ?? '') === '1 to 2 years' ? 'selected' : ''; ?>>1 to 2 years</option>
                                            <option value="2 to 5 years" <?php echo ($_POST['experience_level'] ?? '') === '2 to 5 years' ? 'selected' : ''; ?>>2 to 5 years</option>
                                            <option value="5 to 10 years" <?php echo ($_POST['experience_level'] ?? '') === '5 to 10 years' ? 'selected' : ''; ?>>5 to 10 years</option>
                                            <option value="10+ years" <?php echo ($_POST['experience_level'] ?? '') === '10+ years' ? 'selected' : ''; ?>>10+ years</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="job_type" class="form-label fw-bold">
                                        <i class="fas fa-clock me-2" style="color: var(--primary-color);"></i>Job Type<span class="required-indicator">*</span>
                                    </label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-calendar-alt input-icon"></i>
                                        <select class="form-select form-select-lg" name="job_type" required>
                                            <option value="">Select Job Type</option>
                                            <option value="Full Time" <?php echo ($_POST['job_type'] ?? '') === 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                                            <option value="Part Time" <?php echo ($_POST['job_type'] ?? '') === 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                                            <option value="Freelance" <?php echo ($_POST['job_type'] ?? '') === 'Freelance' ? 'selected' : ''; ?>>Freelance</option>
                                            <option value="Internship" <?php echo ($_POST['job_type'] ?? '') === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                            <option value="Contract-Based" <?php echo ($_POST['job_type'] ?? '') === 'Contract-Based' ? 'selected' : ''; ?>>Contract-Based</option>
                                            <option value="Temporary" <?php echo ($_POST['job_type'] ?? '') === 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                                            <option value="Work From Home" <?php echo ($_POST['job_type'] ?? '') === 'Work From Home' ? 'selected' : ''; ?>>Work From Home</option>
                                            <option value="On-Site" <?php echo ($_POST['job_type'] ?? '') === 'On-Site' ? 'selected' : ''; ?>>On-Site</option>
                                            <option value="Hybrid" <?php echo ($_POST['job_type'] ?? '') === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                            <option value="Seasonal" <?php echo ($_POST['job_type'] ?? '') === 'Seasonal' ? 'selected' : ''; ?>>Seasonal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="education_requirement" class="form-label fw-bold">
                                    <i class="fas fa-graduation-cap me-2" style="color: var(--primary-color);"></i>Education Requirement
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-university input-icon"></i>
                                    <select class="form-select form-select-lg" name="education_requirement">
                                        <option value="">Select Education Level (Optional)</option>
                                        <option value="Elementary" <?php echo ($_POST['education_requirement'] ?? '') === 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                        <option value="Junior High School" <?php echo ($_POST['education_requirement'] ?? '') === 'Junior High School' ? 'selected' : ''; ?>>Junior High School</option>
                                        <option value="Senior High School" <?php echo ($_POST['education_requirement'] ?? '') === 'Senior High School' ? 'selected' : ''; ?>>Senior High School</option>
                                        <option value="Junior College" <?php echo ($_POST['education_requirement'] ?? '') === 'Junior College' ? 'selected' : ''; ?>>Junior College</option>
                                        <option value="Graduate Studies" <?php echo ($_POST['education_requirement'] ?? '') === 'Graduate Studies' ? 'selected' : ''; ?>>Graduate Studies</option>
                                        <option value="Post Graduate" <?php echo ($_POST['education_requirement'] ?? '') === 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                        <option value="Senior College" <?php echo ($_POST['education_requirement'] ?? '') === 'Senior College' ? 'selected' : ''; ?>>Senior College</option>
                                        <option value="College Graduate" <?php echo ($_POST['education_requirement'] ?? '') === 'College Graduate' ? 'selected' : ''; ?>>College Graduate</option>
                                        <option value="N/A" <?php echo ($_POST['education_requirement'] ?? '') === 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                    </select>
                                </div>
                                <div class="form-text"><i class="fas fa-info-circle me-1"></i>Optional education level requirement</div>
                            </div>

                            <div class="mb-4">
                                <label for="qualification" class="form-label fw-bold">
                                    <i class="fas fa-certificate me-2" style="color: var(--primary-color);"></i>Qualification
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-award input-icon"></i>
                                    <textarea class="form-control" name="qualification" id="qualification" rows="5" placeholder="Specify any additional qualifications, certifications, licenses, or special requirements needed for this position..."><?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-text"><i class="fas fa-info-circle me-1"></i>Optional: List specific qualifications, certifications, or licenses required</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="salary_range" class="form-label fw-bold">
                                        <i class="fas fa-money-bill-wave me-2" style="color: var(--primary-color);"></i>Salary Range<span class="required-indicator">*</span>
                                    </label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-peso-sign input-icon"></i>
                                        <select class="form-select form-select-lg" name="salary_range" required>
                                            <option value="">Select Salary Range</option>
                                            <option value="₱15,000 - ₱25,000" <?php echo $salaryRangeValue === '₱15,000 - ₱25,000' ? 'selected' : ''; ?>>₱15,000 - ₱25,000</option>
                                            <option value="₱25,000 - ₱35,000" <?php echo $salaryRangeValue === '₱25,000 - ₱35,000' ? 'selected' : ''; ?>>₱25,000 - ₱35,000</option>
                                            <option value="₱35,000 - ₱50,000" <?php echo $salaryRangeValue === '₱35,000 - ₱50,000' ? 'selected' : ''; ?>>₱35,000 - ₱50,000</option>
                                            <option value="₱50,000 - ₱75,000" <?php echo $salaryRangeValue === '₱50,000 - ₱75,000' ? 'selected' : ''; ?>>₱50,000 - ₱75,000</option>
                                            <option value="₱75,000 - ₱100,000" <?php echo $salaryRangeValue === '₱75,000 - ₱100,000' ? 'selected' : ''; ?>>₱75,000 - ₱100,000</option>
                                            <option value="₱100,000+" <?php echo $salaryRangeValue === '₱100,000+' ? 'selected' : ''; ?>>₱100,000+</option>
                                            <option value="Negotiable" <?php echo $salaryRangeValue === 'Negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="employees_required" class="form-label fw-bold">
                                        <i class="fas fa-users me-2" style="color: var(--primary-color);"></i>Number of Employees<span class="required-indicator">*</span>
                                    </label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-user-friends input-icon"></i>
                                        <select class="form-select form-select-lg" name="employees_required" required>
                                            <option value="">Select Number of Employees</option>
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($_POST['employees_required'] ?? '') === (string)$i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="deadline" class="form-label fw-bold">
                                        <i class="fas fa-calendar-times me-2" style="color: var(--primary-color);"></i>Application Deadline
                                    </label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-calendar input-icon"></i>
                                        <input type="date" class="form-control form-control-lg" name="deadline" value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            </div>

                            <!-- Job Description & Requirements Section -->
                            <div class="form-section mb-5">
                                <h5 class="section-title mb-4">
                                    <i class="fas fa-file-alt me-2" style="color: var(--primary-color);"></i>
                                    Job Description & Requirements
                                </h5>

                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">
                                    <i class="fas fa-align-left me-2" style="color: var(--primary-color);"></i>Job Description<span class="required-indicator">*</span>
                                </label>
                                <textarea class="form-control" name="description" id="description" rows="8" required placeholder="Describe the job role, responsibilities, and what you're looking for in a candidate..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="requirements" class="form-label fw-bold">
                                    <i class="fas fa-tools me-2" style="color: var(--primary-color);"></i>Required Skills<span class="required-indicator">*</span>
                                </label>
                                <div class="input-group-custom">
                                    <i class="fas fa-check-circle input-icon"></i>
                                    <select class="form-select form-select-lg" name="requirements[]" id="requirements" multiple style="height: 150px;" required>
                                        <option value="">Select Job Title first to see available skills</option>
                                    </select>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Select a job title first, then hold Ctrl (Windows) or Cmd (Mac) to select multiple required skills.
                                </div>
                            </div>
                            </div>

                            <div class="section-divider"></div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary-custom btn-lg me-3" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Post Job
                                </button>
                                <button type="button" class="btn btn-secondary-custom btn-lg" onclick="document.querySelector('form').reset();">
                                    <i class="fas fa-redo me-2"></i>Clear Form
                                </button>
                            </div>
                        </form>
                        <script>
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category_id');
            const requirementsSelect = document.getElementById('requirements');
            const categorySkills = <?php echo $categorySkillOptionsJson; ?>;
            const titleToCategoryKey = <?php echo $jobTitleToCategoryKeyJson; ?>;
            const titleToCategoryOption = <?php echo $jobTitleToCategoryOptionJson; ?>;
            const titleToSkills = <?php echo $jobTitleToSkillsJson; ?>;
            const titleToQualifications = <?php echo $jobTitleToQualificationsJson; ?>;
            const selectedRequirement = <?php echo json_encode($requirementsValue, JSON_UNESCAPED_UNICODE); ?>;
            const titleDescriptions = <?php echo $jobTitleDescriptionsJson; ?>;

            const normalizeKey = (value) => (value || '').toLowerCase().trim();
            const descriptionField = document.getElementById('description');
            let lastAutoDescription = '';

            const updateRequirements = (skills, preferredSelection = '') => {
                // Store currently selected value
                const currentSelected = requirementsSelect.value || '';
                
                // Clear the dropdown
                requirementsSelect.innerHTML = '';
                
                if (!skills || skills.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'Select Job Title first to see available skills';
                    requirementsSelect.appendChild(option);
                    return;
                }

                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Required Skill';
                requirementsSelect.appendChild(defaultOption);

                // Add all skills as options
                skills.forEach((skill) => {
                    const option = document.createElement('option');
                    option.value = skill;
                    option.textContent = skill;
                    // Preserve selection if it was previously selected or matches preferred selection
                    if (Array.isArray(preferredSelection)) {
                        if (preferredSelection.includes(skill)) option.selected = true;
                    } else if (typeof preferredSelection === 'string' && preferredSelection.includes(',')) {
                        const preferredArray = preferredSelection.split(',').map(s => s.trim());
                        if (preferredArray.includes(skill)) option.selected = true;
                    } else if (currentSelected === skill || preferredSelection === skill) {
                        option.selected = true;
                    }
                    requirementsSelect.appendChild(option);
                });
            };

            const updateRequirementsByKey = (categoryKey, preferredSelection = '') => {
                const skills = categorySkills[normalizeKey(categoryKey)] || [];
                updateRequirements(skills, preferredSelection);
            };

            const updateRequirementsByCategorySelect = (preferredSelection = '') => {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryName = selectedOption ? selectedOption.dataset.categoryName : '';
                updateRequirementsByKey(categoryName, preferredSelection);
            };

            const findCategoryOptionByKey = (categoryKey) => {
                const normalizedKey = normalizeKey(categoryKey);
                if (!normalizedKey) return null;
                return Array.from(categorySelect.options).find((option) => {
                    const optionName = normalizeKey(option.dataset.categoryName || '');
                    if (!optionName) return false;
                    return optionName === normalizedKey || optionName.includes(normalizedKey) || normalizedKey.includes(optionName);
                }) || null;
            };

            const syncFromTitle = (forceCategoryUpdate = true) => {
                const titleSelect = document.getElementById('title');
                if (!titleSelect) return;
                const titleValue = titleSelect.value || '';
                
                // Automatically set category based on job title
                if (forceCategoryUpdate && titleToCategoryOption[titleValue] && categorySelect) {
                    const categoryOptionValue = titleToCategoryOption[titleValue];
                    // Find and select the matching category option
                    const categoryOption = Array.from(categorySelect.options).find(option => {
                        return option.value === categoryOptionValue || 
                               option.dataset.categoryName === categoryOptionValue ||
                               option.textContent.includes(categoryOptionValue);
                    });
                    if (categoryOption) {
                        categoryOption.selected = true;
                        // Trigger change event to update requirements if needed
                        categorySelect.dispatchEvent(new Event('change'));
                    }
                }
                
                // Check if this job title has specific skills
                const preferredSelection = Array.isArray(selectedRequirement) ? (selectedRequirement[0] || '') : (selectedRequirement || '');
                if (titleToSkills[titleValue] && titleToSkills[titleValue].length > 0) {
                    // Use job-title-specific skills
                    updateRequirements(titleToSkills[titleValue], preferredSelection);
                } else {
                    // Fall back to category-based skills
                    const categoryKey = titleToCategoryKey[titleValue] || '';
                    if (!categoryKey) return;

                    if (forceCategoryUpdate) {
                        const matchedOption = findCategoryOptionByKey(categoryKey);
                        if (matchedOption) {
                            matchedOption.selected = true;
                        }
                    }

                    updateRequirementsByKey(categoryKey, preferredSelection);
                }
            };

            const syncDescriptionFromTitle = () => {
                if (!descriptionField) return;
                const titleSelect = document.getElementById('title');
                if (!titleSelect) return;
                const titleValue = titleSelect.value || '';
                const newDescription = titleDescriptions[titleValue] || '';
                if (!newDescription) return;

                const currentDescription = descriptionField.value.trim();
                if (!currentDescription || currentDescription === lastAutoDescription) {
                    descriptionField.value = newDescription;
                    lastAutoDescription = newDescription;
                    descriptionField.style.height = 'auto';
                    descriptionField.style.height = descriptionField.scrollHeight + 'px';
                }
            };

            const syncQualificationFromTitle = () => {
                const qualificationField = document.getElementById('qualification');
                if (!qualificationField) return;
                const titleSelect = document.getElementById('title');
                if (!titleSelect) return;
                const titleValue = titleSelect.value || '';
                const newQualification = titleToQualifications[titleValue] || '';
                
                // Automatically populate qualifications based on job title
                if (newQualification) {
                    qualificationField.value = newQualification;
                    qualificationField.style.height = 'auto';
                    qualificationField.style.height = qualificationField.scrollHeight + 'px';
                }
            };

            if (categorySelect && requirementsSelect) {
                // Initialize requirements based on current state
                const titleSelect = document.getElementById('title');
                const preferredSelection = Array.isArray(selectedRequirement) ? (selectedRequirement[0] || '') : (selectedRequirement || '');
                
                if (titleSelect && titleSelect.value) {
                    syncFromTitle(true);
                } else {
                    updateRequirementsByCategorySelect(preferredSelection);
                }

                categorySelect.addEventListener('change', () => {
                    // Only update if no job title is selected, or if job title doesn't have specific skills
                    if (!titleSelect || !titleSelect.value || !titleToSkills[titleSelect.value]) {
                        updateRequirementsByCategorySelect('');
                    }
                });

                if (titleSelect) {
                    titleSelect.addEventListener('change', () => {
                        syncFromTitle(true);
                        syncDescriptionFromTitle();
                        syncQualificationFromTitle();
                    });
                }

                if (titleSelect?.value && !categorySelect.value) {
                    syncFromTitle(true);
                }

                if (titleSelect?.value) {
                    syncDescriptionFromTitle();
                    syncQualificationFromTitle();
                }
                
            }

            // Initialize Philippines Address Selector for Job Location
            const PSGC_BASE_URL = 'https://psgc.gitlab.io/api';

            async function fetchPsgcJson(url) {
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) {
                    throw new Error('Failed to load location data.');
                }
                return response.json();
            }

            function setSelectOptions(select, items, placeholder) {
                select.innerHTML = '';
                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                select.appendChild(placeholderOption);

                items
                    .slice()
                    .sort((a, b) => a.name.localeCompare(b.name))
                    .forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.code;
                        option.textContent = item.name;
                        select.appendChild(option);
                    });
            }

            function initJobLocationAddress() {
                const citySelect = document.getElementById('job_city');
                const barangaySelect = document.getElementById('job_barangay');
                const outputInput = document.getElementById('job_location');

                if (!citySelect || !barangaySelect || !outputInput) {
                    return;
                }

                const state = {
                    cityName: '',
                    barangayName: ''
                };

                const resetSelect = (select, placeholder) => {
                    select.disabled = true;
                    select.removeAttribute('required');
                    setSelectOptions(select, [], placeholder);
                };

                const updateOutput = () => {
                    const hasCity = Boolean(state.cityName);
                    const hasBarangay = Boolean(state.barangayName);

                    if (hasCity && hasBarangay) {
                        outputInput.value = `${state.barangayName}, ${state.cityName}`;
                    } else {
                        outputInput.value = '';
                    }
                };

                // Try to parse existing location value and find matching codes
                const existingLocation = citySelect.dataset.locationValue || '';
                let preset = {
                    cityCode: citySelect.dataset.selected || '',
                    barangayCode: barangaySelect.dataset.selected || '',
                    cityName: '',
                    barangayName: ''
                };
                let isPrefill = Boolean(preset.cityCode);

                // If we have location value but no codes, try to find them by name
                if (existingLocation && !preset.cityCode) {
                    const locationParts = existingLocation.split(',').map(p => p.trim());
                    // Format could be: "Barangay, City" or "Barangay, City, Province, Region"
                    if (locationParts.length >= 2) {
                        // Always take first two parts as Barangay and City
                        preset.barangayName = locationParts[0] || '';
                        preset.cityName = locationParts[1] || '';
                    }
                }

                // Load all cities from all regions
                const loadAllCities = async () => {
                    try {
                        citySelect.disabled = true;
                        citySelect.innerHTML = '<option value="">Loading cities...</option>';
                        
                        // Fetch all regions first
                        const regions = await fetchPsgcJson(`${PSGC_BASE_URL}/regions/`);
                        const allCities = [];
                        const cityMap = new Map(); // To avoid duplicates

                        // Load cities from each region
                        for (const region of regions) {
                            try {
                                // Try to get cities directly from region
                                const cities = await fetchPsgcJson(`${PSGC_BASE_URL}/regions/${region.code}/cities-municipalities/`);
                                cities.forEach(city => {
                                    if (!cityMap.has(city.code)) {
                                        cityMap.set(city.code, city);
                                        allCities.push(city);
                                    }
                                });
                            } catch (error) {
                                // If region doesn't have direct cities, try provinces
                                try {
                                    const provinces = await fetchPsgcJson(`${PSGC_BASE_URL}/regions/${region.code}/provinces/`);
                                    for (const province of provinces) {
                                        try {
                                            const cities = await fetchPsgcJson(`${PSGC_BASE_URL}/provinces/${province.code}/cities-municipalities/`);
                                            cities.forEach(city => {
                                                if (!cityMap.has(city.code)) {
                                                    cityMap.set(city.code, city);
                                                    allCities.push(city);
                                                }
                                            });
                                        } catch (err) {
                                            // Skip if error loading cities from province
                                        }
                                    }
                                } catch (err) {
                                    // Skip if error loading provinces
                                }
                            }
                        }

                        // Sort cities alphabetically
                        allCities.sort((a, b) => a.name.localeCompare(b.name));
                        
                        // Set options
                        setSelectOptions(citySelect, allCities, 'Select City/Municipality');
                        citySelect.disabled = false;
                        
                        // Try to find city by name if we have preset.cityName
                        if (preset.cityName && !preset.cityCode) {
                            const foundCity = allCities.find(c => 
                                c.name.toLowerCase() === preset.cityName.toLowerCase() ||
                                c.name.toLowerCase().includes(preset.cityName.toLowerCase()) ||
                                preset.cityName.toLowerCase().includes(c.name.toLowerCase())
                            );
                            if (foundCity) {
                                preset.cityCode = foundCity.code;
                                citySelect.value = foundCity.code;
                                citySelect.dispatchEvent(new Event('change'));
                            }
                        } else if (preset.cityCode) {
                            citySelect.value = preset.cityCode;
                            citySelect.dispatchEvent(new Event('change'));
                        }
                    } catch (error) {
                        setSelectOptions(citySelect, [], 'Unable to load cities');
                        citySelect.disabled = true;
                    }
                };

                const loadBarangays = async (cityCode) => {
                    // Clear and disable barangay select first
                    resetSelect(barangaySelect, 'Loading barangays...');
                    
                    try {
                        const barangays = await fetchPsgcJson(`${PSGC_BASE_URL}/cities-municipalities/${cityCode}/barangays/`);
                        setSelectOptions(barangaySelect, barangays, 'Select Barangay');
                        barangaySelect.disabled = false;
                        barangaySelect.setAttribute('required', 'required');
                        
                        // Try to find barangay by name if we have preset.barangayName
                        if (preset.barangayName && !preset.barangayCode) {
                            const foundBarangay = barangays.find(b => 
                                b.name.toLowerCase() === preset.barangayName.toLowerCase() ||
                                b.name.toLowerCase().includes(preset.barangayName.toLowerCase()) ||
                                preset.barangayName.toLowerCase().includes(b.name.toLowerCase())
                            );
                            if (foundBarangay) {
                                preset.barangayCode = foundBarangay.code;
                                barangaySelect.value = foundBarangay.code;
                                barangaySelect.dispatchEvent(new Event('change'));
                            }
                        } else if (preset.barangayCode) {
                            barangaySelect.value = preset.barangayCode;
                            barangaySelect.dispatchEvent(new Event('change'));
                        }
                    } catch (error) {
                        resetSelect(barangaySelect, 'Unable to load barangays');
                        console.error('Error loading barangays:', error);
                    }
                };

                citySelect.addEventListener('change', async function() {
                    const selectedCityCode = this.value;
                    const selectedCityName = this.options[this.selectedIndex]?.text || '';
                    
                    // Reset barangay state
                    state.cityName = selectedCityName;
                    state.barangayName = '';
                    outputInput.value = '';

                    // Clear and disable barangay dropdown immediately
                    resetSelect(barangaySelect, 'Select Barangay');

                    if (!selectedCityCode) {
                        updateOutput();
                        return;
                    }

                    // Load barangays only for the selected city
                    await loadBarangays(selectedCityCode);
                    updateOutput();
                });

                barangaySelect.addEventListener('change', function() {
                    state.barangayName = this.options[this.selectedIndex]?.text || '';
                    updateOutput();
                    if (isPrefill) {
                        isPrefill = false;
                    }
                });

                resetSelect(barangaySelect, 'Select Barangay');
                loadAllCities();
            }

            // Initialize job location address selector
            initJobLocationAddress();

            // Show/hide courses field based on job title selection
            const jobTitleSelect = document.getElementById('title');
            const coursesFieldContainer = document.getElementById('courses_field_container');
            const coursesSelect = document.getElementById('courses');
            
            function toggleCoursesField() {
                const selectedTitle = jobTitleSelect.value;
                if (selectedTitle === 'College Instructor') {
                    coursesFieldContainer.style.display = 'block';
                    coursesSelect.setAttribute('required', 'required');
                } else {
                    coursesFieldContainer.style.display = 'none';
                    coursesSelect.removeAttribute('required');
                    // Clear the courses field when hidden
                    coursesSelect.value = '';
                }
            }
            
            // Check on page load
            if (jobTitleSelect && coursesFieldContainer && coursesSelect) {
                toggleCoursesField();
                // Listen for changes
                jobTitleSelect.addEventListener('change', toggleCoursesField);
            }
        });
    </script>
</body>
</html>
