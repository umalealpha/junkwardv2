<?php
/**
 * AWS S3 Configuration for Employer Group File Uploads
 * 
 * Add these settings to your .env file:
 * 
 * AWS_ACCESS_KEY_ID=your_access_key_here
 * AWS_SECRET_ACCESS_KEY=your_secret_key_here
 * AWS_DEFAULT_REGION=us-east-1
 * AWS_BUCKET=your-bucket-name
 * AWS_USE_PATH_STYLE_ENDPOINT=false
 * 
 * Make sure your S3 bucket has the following folder structure:
 * - employer-groups/
 *   - certificates/
 *   - tax-certificates/
 *   - proof-address/
 * 
 * S3 Bucket Policy Example (adjust as needed):
 * {
 *   "Version": "2012-10-17",
 *   "Statement": [
 *     {
 *       "Sid": "AllowEmployerGroupUploads",
 *       "Effect": "Allow",
 *       "Principal": {
 *         "AWS": "arn:aws:iam::YOUR_ACCOUNT_ID:user/YOUR_IAM_USER"
 *       },
 *       "Action": [
 *         "s3:PutObject",
 *         "s3:PutObjectAcl",
 *         "s3:GetObject",
 *         "s3:DeleteObject"
 *       ],
 *       "Resource": "arn:aws:s3:::your-bucket-name/employer-groups/*"
 *     }
 *   ]
 * }
 */

return [
    's3' => [
        'bucket' => env('AWS_BUCKET', 'your-bucket-name'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'folders' => [
            'certificates' => 'employer-groups/certificates',
            'tax_certificates' => 'employer-groups/tax-certificates',
            'proof_address' => 'employer-groups/proof-address',
        ],
        'allowed_mimes' => [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'application/pdf'
        ],
        'max_file_size' => 10240, // 10MB in KB
    ]
];
