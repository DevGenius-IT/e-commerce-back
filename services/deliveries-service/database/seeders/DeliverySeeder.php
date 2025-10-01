<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\SalePoint;
use App\Models\Status;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salePoints = SalePoint::all();
        $statuses = Status::all();
        
        if ($salePoints->isEmpty() || $statuses->isEmpty()) {
            $this->command->warn('Please run StatusSeeder and SalePointSeeder first!');
            return;
        }

        $carriers = ['Chronopost', 'Colissimo', 'DPD', 'UPS', 'DHL', 'Relay'];
        $deliveryMethods = ['standard', 'express', 'pickup'];
        
        // Sample addresses
        $addresses = [
            "15 Avenue des Champs-Élysées, 75008 Paris",
            "42 Rue de Rivoli, 75001 Paris",
            "78 Boulevard Saint-Germain, 75006 Paris",
            "123 Avenue de la République, 69003 Lyon",
            "56 Cours Mirabeau, 13100 Aix-en-Provence",
            "89 Rue Saint-Catherine, 33000 Bordeaux",
            "234 Avenue Jean Médecin, 06000 Nice",
            "67 Place Wilson, 31000 Toulouse",
            "45 Rue de la Paix, 59000 Lille",
            "91 Cours Franklin Roosevelt, 69006 Lyon",
        ];

        $recipients = [
            ['name' => 'Sophie Martin', 'phone' => '+33 6 12 34 56 78'],
            ['name' => 'Pierre Dubois', 'phone' => '+33 6 87 65 43 21'],
            ['name' => 'Marie Leroy', 'phone' => '+33 6 23 45 67 89'],
            ['name' => 'Jean Bernard', 'phone' => '+33 6 98 76 54 32'],
            ['name' => 'Claire Rousseau', 'phone' => '+33 6 34 56 78 90'],
            ['name' => 'Antoine Moreau', 'phone' => '+33 6 45 67 89 01'],
            ['name' => 'Isabelle Simon', 'phone' => '+33 6 56 78 90 12'],
            ['name' => 'Julien Garnier', 'phone' => '+33 6 67 89 01 23'],
            ['name' => 'Nathalie Fabre', 'phone' => '+33 6 78 90 12 34'],
            ['name' => 'Thomas Mercier', 'phone' => '+33 6 89 01 23 45'],
        ];

        // Create 50 deliveries with various statuses and scenarios
        for ($i = 1; $i <= 50; $i++) {
            $salePoint = $salePoints->random();
            $status = $statuses->random();
            $method = $deliveryMethods[array_rand($deliveryMethods)];
            $carrier = $carriers[array_rand($carriers)];
            $recipient = $recipients[array_rand($recipients)];
            $address = $addresses[array_rand($addresses)];
            
            // Calculate dates based on status
            $createdAt = Carbon::now()->subDays(rand(1, 30));
            $estimatedDelivery = $createdAt->copy()->addDays($method === 'express' ? 1 : ($method === 'pickup' ? 2 : 3));
            
            $shippedAt = null;
            $actualDelivery = null;
            
            // Set shipped date for certain statuses
            if (in_array($status->name, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_failed', 'returned'])) {
                $shippedAt = $createdAt->copy()->addDays(rand(1, 2));
            }
            
            // Set delivery date for delivered items
            if ($status->name === 'delivered') {
                $actualDelivery = $shippedAt->copy()->addDays(rand(1, 3));
            }
            
            // Calculate shipping cost based on method
            $shippingCost = match ($method) {
                'express' => rand(15, 25),
                'standard' => rand(5, 12),
                'pickup' => rand(0, 5),
                default => rand(5, 15),
            };

            $deliveryData = [
                'order_id' => rand(1, 40), // Reference to orders from orders-service
                'sale_point_id' => $salePoint->id,
                'status_id' => $status->id,
                'delivery_method' => $method,
                'shipping_cost' => $shippingCost,
                'delivery_address' => $address,
                'estimated_delivery_date' => $estimatedDelivery,
                'actual_delivery_date' => $actualDelivery,
                'shipped_at' => $shippedAt,
                'carrier_name' => $carrier,
                'carrier_tracking_number' => $carrier . rand(100000000, 999999999),
                'recipient_name' => $recipient['name'],
                'recipient_phone' => $recipient['phone'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            // Add special instructions for some deliveries
            if (rand(1, 100) <= 30) {
                $instructions = [
                    'Laisser en point relais si absent',
                    'Sonner deux fois',
                    'Code porte : 1234A',
                    'Livraison uniquement en matinée',
                    'Appeler avant livraison',
                    'Fragile - manipuler avec précaution',
                    'Ne pas laisser sans signature',
                    'Accès par la cour intérieure',
                ];
                $deliveryData['special_instructions'] = $instructions[array_rand($instructions)];
            }

            // Add delivery notes for completed deliveries
            if (in_array($status->name, ['delivered', 'delivery_failed', 'returned'])) {
                $notes = [
                    'Livré en main propre au destinataire',
                    'Déposé dans la boîte aux lettres',
                    'Remis au voisin (M. Dupont)',
                    'Laissé au gardien de l\'immeuble',
                    'Destinataire absent - avis de passage',
                    'Adresse introuvable',
                    'Refusé par le destinataire',
                    'Livré au point relais le plus proche',
                ];
                $deliveryData['delivery_notes'] = $notes[array_rand($notes)];
            }

            // Set carrier details as JSON
            $deliveryData['carrier_details'] = json_encode([
                'service_type' => $method === 'express' ? 'Express 24h' : 'Standard',
                'weight_kg' => rand(1, 15) / 10, // Random weight between 0.1 and 1.5 kg
                'dimensions' => [
                    'length' => rand(10, 40),
                    'width' => rand(10, 30),
                    'height' => rand(5, 20),
                ],
                'insurance_value' => rand(50, 500),
            ]);

            Delivery::create($deliveryData);
        }
    }
}