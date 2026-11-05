<?php
/**
 * Default copy for patient follow-up reminders (overridable in Admin → Messaging).
 */
return [
    'default_sms' => 'RHU Magallanes: Reminder for {patient_name}. Follow-up check-up on {follow_up_date}. {instructions} Questions? Call {facility_phone}.',
    'default_email_subject' => 'Follow-up reminder — {follow_up_date}',
    'default_email_body' => "Dear {patient_name},\n\nThis is a reminder from {facility_name} for your follow-up check-up on {follow_up_date}.\n\n{instructions}\n\nFor inquiries: {facility_phone}\n\nThank you.",
];
