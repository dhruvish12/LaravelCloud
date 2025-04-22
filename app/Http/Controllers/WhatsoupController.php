<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Geocoder;

class WhatsoupController extends Controller
{
    /**
     * Send a PDF document via WhatsApp using Twilio
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendPdf(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Debug logging
        Log::info('Starting PDF send attempt');

        // Get Twilio credentials from .env
        $twilioSid = config('services.twilio.sid');
        $twilioToken = config('services.twilio.token');
        $twilioWhatsAppNumber = config('services.twilio.whatsapp_from');

        // Check for credentials
        if (!$twilioSid || !$twilioToken || !$twilioWhatsAppNumber) {
            Log::error('Missing Twilio credentials');
            return response()->json(['error' => 'Twilio credentials not configured'], 500);
        }

        // Phone number formatting
        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        
        // Ensure phone has country code
        if (strlen($phone) < 10) {
            return response()->json(['error' => 'Invalid phone number format'], 422);
        }
        
        // Format recipient and sender numbers for WhatsApp
        $recipientNumber = 'whatsapp:+' . $phone;
        $fromNumber = 'whatsapp:' . $twilioWhatsAppNumber;
        
        // Remove any + from the from number if present
        $fromNumber = str_replace('whatsapp:+', 'whatsapp:', $fromNumber);

        // Debug phone numbers
        Log::info('Phone numbers:', [
            'recipient' => $recipientNumber,
            'from' => $fromNumber
        ]);

        // Verify PDF exists and is accessible
        $pdfPath = public_path('pdfs/resume.pdf');
        $pdfUrl = url('pdfs/resume.pdf');

        if (!file_exists($pdfPath)) {
            Log::error('PDF file not found at: ' . $pdfPath);
            return response()->json(['error' => 'PDF file not found'], 404);
        }

        // Debug PDF URL
        Log::info('PDF URL: ' . $pdfUrl);

        // Send message with detailed error catching
        try {
            // Initialize Twilio client
            $client = new Client($twilioSid, $twilioToken);
            
            // Send the message
            $message = $client->messages->create($recipientNumber, [
                'from' => $fromNumber,
                'body' => 'Here is your PDF document',
                'mediaUrl' => [$pdfUrl]
            ]);

            Log::info('Message sent successfully', ['message_sid' => $message->sid]);

            return response()->json([
                'success' => true,
                'message' => 'PDF sent successfully',
                'sid' => $message->sid
            ]);
        } catch (RestException $e) {
            Log::error('Twilio error: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'status' => $e->getStatusCode()
            ]);
            return response()->json(['error' => 'Failed to send PDF: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('General error: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }



    

    public function getLatLngFromAddress()
    {
        $address = "Surat, Gujarat, India, 395001"; // Adding postal code might help
    
        $geocoder = app('geocoder');
        try {
            $result = $geocoder->geocode($address)->get();
    
            if ($result->isEmpty()) {
                return response()->json(['error' => 'Location not found'], 404);
            }
    
            $coordinates = $result->first()->getCoordinates();
    
            return response()->json([
                'latitude' => $coordinates->getLatitude(),
                'longitude' => $coordinates->getLongitude(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
