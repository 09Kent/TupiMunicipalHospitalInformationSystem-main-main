<?php
if (!class_exists('PgsqlCompatPdo', false)) {
    class PgsqlCompatPdo extends PDO
    {
        private static ?string $pattern = null;

        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
        {
            $query = self::rewriteSql($query);
            return parent::query($query, ...$fetchModeArgs);
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            $query = self::rewriteSql($query);
            return parent::prepare($query, $options);
        }

        public function exec(string $statement): int|false
        {
            $statement = self::rewriteSql($statement);
            return parent::exec($statement);
        }

        public function lastInsertId(?string $name = null): string|false
        {
            if ($name !== null && $name !== '') {
                return parent::lastInsertId($name);
            }

            try {
                $stmt = parent::query('SELECT lastval()');
                if ($stmt) {
                    $val = $stmt->fetchColumn();
                    if ($val !== false && $val !== null) {
                        return (string) $val;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore if lastval() is not yet initialized in current session
            }

            return parent::lastInsertId($name);
        }

        public static function rewriteSql(string $sql): string
        {
            if (self::$pattern === null) {
                $cols = array (
  0 => 'LifestyleRecommendations',
  1 => 'PhilHealthAccreditation',
  2 => 'PreviousHospitalization',
  3 => 'ConsultationStartedAt',
  4 => 'PhilHealthCoveredRate',
  5 => 'RelationshipToPatient',
  6 => 'ComplaintConditionID',
  7 => 'ComplaintDescription',
  8 => 'PhilHealthDeduction',
  9 => 'AggravatingFactors',
  10 => 'CurrentMedications',
  11 => 'DosageInstructions',
  12 => 'EmergencyContactID',
  13 => 'ExistingConditions',
  14 => 'DoctorSpecialtyID',
  15 => 'QuantityDispensed',
  16 => 'ReferringDoctorID',
  17 => 'TargetSpecialtyID',
  18 => 'ConsultationType',
  19 => 'ContactExtension',
  20 => 'DOHAccreditation',
  21 => 'DiscountEligible',
  22 => 'EmergencyHotline',
  23 => 'FollowUpSchedule',
  24 => 'HeadOfDepartment',
  25 => 'MedicalHistoryID',
  26 => 'OxygenSaturation',
  27 => 'PatientSymptomID',
  28 => 'PrescriptionCode',
  29 => 'ProcessingStatus',
  30 => 'PurposeOfRequest',
  31 => 'RelievingFactors',
  32 => 'AppointmentDate',
  33 => 'AppointmentTime',
  34 => 'CertificateCode',
  35 => 'CertificateType',
  36 => 'ClinicalSummary',
  37 => 'ConfidenceLevel',
  38 => 'ConsultationFee',
  39 => 'ExperienceYears',
  40 => 'ItemDescription',
  41 => 'MedicalDirector',
  42 => 'PatientCategory',
  43 => 'ReferenceNumber',
  44 => 'RespiratoryRate',
  45 => 'StorageLocation',
  46 => 'AttachmentPath',
  47 => 'BodyLocationID',
  48 => 'ChargeCategory',
  49 => 'CollectionDate',
  50 => 'DepartmentCode',
  51 => 'DepartmentName',
  52 => 'DepartmentType',
  53 => 'DiscountAmount',
  54 => 'DoctorSystemID',
  55 => 'HospitalInfoID',
  56 => 'Interpretation',
  57 => 'MedicationPlan',
  58 => 'PermissionCode',
  59 => 'PermissionName',
  60 => 'PrescriptionID',
  61 => 'Recommendation',
  62 => 'RecordedByName',
  63 => 'RelevanceLevel',
  64 => 'TargetDoctorID',
  65 => 'TurnaroundTime',
  66 => 'AmountInWords',
  67 => 'AppointmentID',
  68 => 'BillingStatus',
  69 => 'BloodPressure',
  70 => 'CertificateID',
  71 => 'ClinicalNotes',
  72 => 'ConditionName',
  73 => 'ConfirmedDate',
  74 => 'ContactNumber',
  75 => 'DiagnosedDate',
  76 => 'DiagnosisName',
  77 => 'DispenserName',
  78 => 'DurationStart',
  79 => 'InvoiceNumber',
  80 => 'LicenseNumber',
  81 => 'ParameterName',
  82 => 'PaymentMethod',
  83 => 'PaymentStatus',
  84 => 'ReceiptNumber',
  85 => 'ReferenceCode',
  86 => 'RequestNumber',
  87 => 'RequestedDate',
  88 => 'RequestorName',
  89 => 'ResponseNotes',
  90 => 'SampleBarcode',
  91 => 'SpecialtyCode',
  92 => 'SpecialtyName',
  93 => 'StandardPrice',
  94 => 'BodySystemID',
  95 => 'CriticalHigh',
  96 => 'CurrentStock',
  97 => 'DepartmentID',
  98 => 'DiscountType',
  99 => 'DispenseCode',
  100 => 'DispenseDate',
  101 => 'ErrorMessage',
  102 => 'FollowUpDate',
  103 => 'HospitalCode',
  104 => 'HospitalName',
  105 => 'Instructions',
  106 => 'IsSystemRole',
  107 => 'LocationCode',
  108 => 'LocationName',
  109 => 'MedicineName',
  110 => 'MovementType',
  111 => 'PasswordHash',
  112 => 'PermissionID',
  113 => 'ProfileImage',
  114 => 'ReferralCode',
  115 => 'RegisteredBy',
  116 => 'Relationship',
  117 => 'ReleasedDate',
  118 => 'ReorderLevel',
  119 => 'ReviewsCount',
  120 => 'SellingPrice',
  121 => 'SpecimenType',
  122 => 'StandardRate',
  123 => 'TaskCategory',
  124 => 'TaskPriority',
  125 => 'TotalPayable',
  126 => 'VitalRemarks',
  127 => 'AllergyType',
  128 => 'BatchNumber',
  129 => 'BedCapacity',
  130 => 'CashierName',
  131 => 'CivilStatus',
  132 => 'CollectedBy',
  133 => 'ComplaintID',
  134 => 'CompletedAt',
  135 => 'ConditionID',
  136 => 'ContactName',
  137 => 'CriticalLow',
  138 => 'DateOfBirth',
  139 => 'DaysExcused',
  140 => 'Description',
  141 => 'DiagnosisID',
  142 => 'DispensedBy',
  143 => 'DurationEnd',
  144 => 'FemaleRange',
  145 => 'GenericName',
  146 => 'GrossAmount',
  147 => 'InventoryID',
  148 => 'NormalRange',
  149 => 'PatientCode',
  150 => 'PaymentDate',
  151 => 'ProcessedBy',
  152 => 'QueueNumber',
  153 => 'QueueStatus',
  154 => 'RequestCode',
  155 => 'RequestType',
  156 => 'ResultValue',
  157 => 'ServiceCode',
  158 => 'ServiceName',
  159 => 'SpecialtyID',
  160 => 'SymptomName',
  161 => 'TaskRemarks',
  162 => 'Temperature',
  163 => 'AmountPaid',
  164 => 'AnalysisID',
  165 => 'Assessment',
  166 => 'BackupType',
  167 => 'BalanceDue',
  168 => 'ClinicRoom',
  169 => 'DispenseID',
  170 => 'DosageForm',
  171 => 'ExpiryDate',
  172 => 'IssuedDate',
  173 => 'MiddleName',
  174 => 'MovementID',
  175 => 'RecordedAt',
  176 => 'RecordedBy',
  177 => 'ReferralID',
  178 => 'RequestUri',
  179 => 'ResultDate',
  180 => 'StackTrace',
  181 => 'Subjective',
  182 => 'SystemCode',
  183 => 'SystemName',
  184 => 'TaskStatus',
  185 => 'TotalCount',
  186 => 'VitalSigns',
  187 => 'Allergies',
  188 => 'AllergyID',
  189 => 'BloodType',
  190 => 'BrandName',
  191 => 'CatalogID',
  192 => 'CreatedAt',
  193 => 'CreatedBy',
  194 => 'Diagnosis',
  195 => 'Education',
  196 => 'ErrorCode',
  197 => 'FirstName',
  198 => 'Frequency',
  199 => 'FrontBack',
  200 => 'HeartRate',
  201 => 'HistoryID',
  202 => 'ICD10Code',
  203 => 'IPAddress',
  204 => 'InvoiceID',
  205 => 'IsPrimary',
  206 => 'IssueDate',
  207 => 'Languages',
  208 => 'MaleRange',
  209 => 'NetAmount',
  210 => 'Objective',
  211 => 'PainScale',
  212 => 'PatientID',
  213 => 'PaymentID',
  214 => 'QueueDate',
  215 => 'RequestID',
  216 => 'Specialty',
  217 => 'SubRegion',
  218 => 'SymptomID',
  219 => 'TaskTitle',
  220 => 'UnitPrice',
  221 => 'UpdatedAt',
  222 => 'UserAgent',
  223 => 'Allergen',
  224 => 'BackupID',
  225 => 'BilledBy',
  226 => 'CalledAt',
  227 => 'Category',
  228 => 'ChargeID',
  229 => 'DocFirst',
  230 => 'DoctorID',
  231 => 'Duration',
  232 => 'FileName',
  233 => 'FileSize',
  234 => 'HeightCm',
  235 => 'ItemCode',
  236 => 'LastName',
  237 => 'Location',
  238 => 'LogoPath',
  239 => 'Priority',
  240 => 'Quantity',
  241 => 'Reaction',
  242 => 'RecordID',
  243 => 'ResultID',
  244 => 'RoleCode',
  245 => 'RoleName',
  246 => 'SampleID',
  247 => 'Severity',
  248 => 'Strength',
  249 => 'SubTotal',
  250 => 'Supplier',
  251 => 'TestCode',
  252 => 'TestName',
  253 => 'TestType',
  254 => 'UnitCost',
  255 => 'UserName',
  256 => 'UserRole',
  257 => 'Username',
  258 => 'WeightKg',
  259 => 'Address',
  260 => 'Details',
  261 => 'DocLast',
  262 => 'DueDate',
  263 => 'DueTime',
  264 => 'ErrorID',
  265 => 'NurseID',
  266 => 'QueueID',
  267 => 'RangeID',
  268 => 'Refills',
  269 => 'Remarks',
  270 => 'TaskDue',
  271 => 'VitalID',
  272 => 'Action',
  273 => 'Clinic',
  274 => 'Dosage',
  275 => 'Gender',
  276 => 'Module',
  277 => 'NoteID',
  278 => 'PlanID',
  279 => 'Rating',
  280 => 'Reason',
  281 => 'RoleID',
  282 => 'Status',
  283 => 'TaskID',
  284 => 'UserID',
  285 => 'Email',
  286 => 'Emoji',
  287 => 'FeeID',
  288 => 'LogID',
  289 => 'Notes',
  290 => 'TaxID',
  291 => 'Title',
  292 => 'Units',
  293 => 'Goal',
  294 => 'Icon',
  295 => 'Plan',
  296 => 'Role',
  297 => 'Type',
  298 => 'Unit',
  299 => 'Age',
  300 => 'BMI',
  301 => 'Bio',
);
                self::$pattern = '/(?<![\'":a-zA-Z0-9_])(' . implode('|', array_map('preg_quote', $cols)) . ')(?![\'":a-zA-Z0-9_])/';
            }
            $sql = preg_replace('/\bDATE_SUB\s*\(\s*(CURDATE\(\)|CURRENT_DATE)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i', "($1 - INTERVAL '$2 DAY')", $sql);
            $sql = preg_replace('/\bDATE_ADD\s*\(\s*(CURDATE\(\)|CURRENT_DATE)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i', "($1 + INTERVAL '$2 DAY')", $sql);
            $sql = preg_replace('/\bCURDATE\(\)/i', 'CURRENT_DATE', $sql);
            $sql = preg_replace('/\bLAST_INSERT_ID\s*\(\s*\)/i', 'lastval()', $sql);
            
            // MySQL GROUP_CONCAT -> PostgreSQL STRING_AGG with ::text cast
            $sql = preg_replace('/\bGROUP_CONCAT\s*\(\s*(DISTINCT\s+)?(.*?)\s+SEPARATOR\s+([\'"].*?[\'"])\s*\)/is', 'STRING_AGG($1($2)::text, $3)', $sql);
            $sql = preg_replace('/\bGROUP_CONCAT\s*\(\s*(DISTINCT\s+)?(.*?)\s*\)/is', "STRING_AGG($1($2)::text, ',')", $sql);


            // MySQL DATE_FORMAT -> PostgreSQL TO_CHAR
            $sql = preg_replace_callback('/\bDATE_FORMAT\s*\(\s*(.*?)\s*,\s*([\'"].*?[\'"])\s*\)/i', function($m) {
                $expr = $m[1];
                $format = trim($m[2], "'\"");
                $map = [
                    '%Y' => 'YYYY',
                    '%y' => 'YY',
                    '%m' => 'MM',
                    '%d' => 'DD',
                    '%b' => 'Mon',
                    '%M' => 'Month',
                    '%H' => 'HH24',
                    '%h' => 'HH12',
                    '%i' => 'MI',
                    '%s' => 'SS',
                ];
                $pgFormat = strtr($format, $map);
                return "TO_CHAR($expr, '$pgFormat')";
            }, $sql);

            // Auto-quote mixed-case AS aliases and replace corresponding unquoted aliases in ORDER BY / HAVING
            if (preg_match_all('/\bAS\s+([a-zA-Z0-9_]*[A-Z][a-zA-Z0-9_]*)\b/i', $sql, $aliasMatches)) {
                $sql = preg_replace('/\bAS\s+([a-zA-Z0-9_]*[A-Z][a-zA-Z0-9_]*)\b/', 'AS "$1"', $sql);
                foreach (array_unique($aliasMatches[1]) as $alias) {
                    $sql = preg_replace('/(?<![\'":a-zA-Z0-9_])' . preg_quote($alias, '/') . '(?![\'":a-zA-Z0-9_])/', '"' . $alias . '"', $sql);
                }
            }
            
            return preg_replace(self::$pattern, '"$1"', $sql);
        }
    }
}