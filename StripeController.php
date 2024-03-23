if(isset($req) && $req->amount != null) {
    // create tripe customer
    $stripeCustomer = null;
    $stripeV2 = new \Stripe\StripeClient([
        'api_key' => $stripe_sk,
        'stripe_version' => '2020-08-27',
    ]);
    try {
        $stripeCustomerName = $checkout->buyer_first_name . " " . $checkout->buyer_last_name;
        $stripeCustomerEmail = $checkout->buyer_email;
        $stripeCustomers = $stripeV2->customers->search([
            'query' => 'name:\''.$stripeCustomerName.'\' AND email:\''.$stripeCustomerEmail.'\'',
        ], 
        ['stripe_account' => $merchant->stripe_user_id??null]);

        if (!$stripeCustomers->data) {
            $stripeCustomer = $stripeV2->customers->create([
                'name' => $stripeCustomerName,
                'email' => $stripeCustomerEmail,
                'metadata' => [
                    'recipient_name' => $checkout->recipient_first_name . " " . $checkout->recipient_last_name,
                    'recipient_email' => $checkout->recipient_email,
                ],
            ], 
            ['stripe_account' => $merchant->stripe_user_id??null]);
        } else {
            // $stripeCustomer = $stripeCustomers->data[0];
            $stripeCustomer = $stripeV2->customers->update($stripeCustomers->data[0]->id,[
                'metadata' => [
                    'recipient_name' => $checkout->recipient_first_name . " " . $checkout->recipient_last_name,
                    'recipient_email' => $checkout->recipient_email,
                ],
            ], 
            ['stripe_account' => $merchant->stripe_user_id??null]);
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        $output = [
            'clientSecret' => null,
            'message' => $msg,
            'redirect_url' => url('/') . "/buy/" . $merchant->buylink_url,
            'is_redirect' => false,
        ];
        return json_encode($output);
    }
    // end create tripe customer

    try {
        $intent = $stripe->paymentIntents->create(
            [
                'customer' => @$stripeCustomer->id,
                'amount' => (int) $checkout->amount, 
                'currency' => $merchant->stripe_currency?? 'aud',
                'application_fee_amount' => $merchant->applicationFeeAmount(),
                // 'on_behalf_of' => $merchant->stripe_user_id,
                // 'transfer_data'=> [
                //     'destination' => $merchant->stripe_user_id
                // ],
                'metadata' => [
                    'merchant_name' => $merchant->name,
                    'merchant_id' => $merchant->id,
                    'checkout_id' => $checkout->id,
                    'purchase_url' => url('/') . "/buy/" . $merchant->buylink_url,
                ],
                'description' => 'Gift-it: '.$checkout->recipient_first_name.' '. $checkout->recipient_last_name .' - '.$reference,
            ], 
            ['stripe_account' => $merchant->stripe_user_id??null],
        );

        // dd($intent);
        $checkout->status = $intent['status'];
        $checkout->save();

        \Log::info("stripe payment intents id " . $intent['id']);
        \Log::info("stripe payment intents status " . $intent['status']);
        \Log::info("stripe payment intents client_seccret " . $intent['client_secret']);
    } catch (\Exception $e) {
        $msg = strpos($e->getMessage(),'does not have access to account')? 'Stripe account connected is invalid on Merchant' : $e->getMessage();
        $output = [
            'clientSecret' => null,
            'message' => $msg,
            'redirect_url' => url('/') . "/buy/" . $merchant->buylink_url,
            'is_redirect' => false,
        ];
        return json_encode($output);
    }

}
$output = [
    'clientSecret' => $intent->client_secret,
    'message' => 'Create intents success',
    'redirect_url' => url('/') . "/buy/" . $merchant->buylink_url,
    'is_redirect' => false,
    'giftCardValue' => $giftCardValue,
];

return json_encode($output);
