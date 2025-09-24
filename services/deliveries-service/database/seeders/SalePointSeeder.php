<?php

namespace Database\Seeders;

use App\Models\SalePoint;
use Illuminate\Database\Seeder;

class SalePointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salePoints = [
            [
                'name' => 'MNS Paris Centre',
                'code' => 'PAR001',
                'address' => '123 Avenue des Champs-Élysées',
                'city' => 'Paris',
                'postal_code' => '75008',
                'phone' => '+33 1 42 65 78 90',
                'email' => 'paris.centre@mns.com',
                'latitude' => 48.8738,
                'longitude' => 2.2950,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-17:00',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Lyon Bellecour',
                'code' => 'LYN001',
                'address' => '45 Place Bellecour',
                'city' => 'Lyon',
                'postal_code' => '69002',
                'phone' => '+33 4 72 56 89 12',
                'email' => 'lyon.bellecour@mns.com',
                'latitude' => 45.7578,
                'longitude' => 4.8320,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:30-18:30',
                    'tuesday' => '09:30-18:30',
                    'wednesday' => '09:30-18:30',
                    'thursday' => '09:30-18:30',
                    'friday' => '09:30-18:30',
                    'saturday' => '10:00-17:00',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Marseille Vieux Port',
                'code' => 'MAR001',
                'address' => '78 Quai du Port',
                'city' => 'Marseille',
                'postal_code' => '13002',
                'phone' => '+33 4 91 54 32 10',
                'email' => 'marseille.port@mns.com',
                'latitude' => 43.2965,
                'longitude' => 5.3698,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '09:30-17:30',
                    'sunday' => '10:00-16:00'
                ]
            ],
            [
                'name' => 'MNS Toulouse Capitole',
                'code' => 'TOU001',
                'address' => '12 Place du Capitole',
                'city' => 'Toulouse',
                'postal_code' => '31000',
                'phone' => '+33 5 61 23 45 67',
                'email' => 'toulouse.capitole@mns.com',
                'latitude' => 43.6043,
                'longitude' => 1.4437,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-17:00',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Nice Promenade',
                'code' => 'NIC001',
                'address' => '25 Promenade des Anglais',
                'city' => 'Nice',
                'postal_code' => '06000',
                'phone' => '+33 4 93 87 65 43',
                'email' => 'nice.promenade@mns.com',
                'latitude' => 43.6956,
                'longitude' => 7.2653,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:30-18:30',
                    'tuesday' => '09:30-18:30',
                    'wednesday' => '09:30-18:30',
                    'thursday' => '09:30-18:30',
                    'friday' => '09:30-18:30',
                    'saturday' => '10:00-18:00',
                    'sunday' => '11:00-17:00'
                ]
            ],
            [
                'name' => 'MNS Bordeaux Centre',
                'code' => 'BOR001',
                'address' => '56 Cours de l\'Intendance',
                'city' => 'Bordeaux',
                'postal_code' => '33000',
                'phone' => '+33 5 56 48 72 91',
                'email' => 'bordeaux.centre@mns.com',
                'latitude' => 44.8414,
                'longitude' => -0.5805,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-17:00',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Lille Grand Place',
                'code' => 'LIL001',
                'address' => '89 Grand Place',
                'city' => 'Lille',
                'postal_code' => '59000',
                'phone' => '+33 3 20 55 44 33',
                'email' => 'lille.grandplace@mns.com',
                'latitude' => 50.6365,
                'longitude' => 3.0635,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-17:00',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Nantes Commerce',
                'code' => 'NAN001',
                'address' => '34 Rue de la Fosse',
                'city' => 'Nantes',
                'postal_code' => '44000',
                'phone' => '+33 2 40 73 82 91',
                'email' => 'nantes.commerce@mns.com',
                'latitude' => 47.2173,
                'longitude' => -1.5534,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:30-18:30',
                    'tuesday' => '09:30-18:30',
                    'wednesday' => '09:30-18:30',
                    'thursday' => '09:30-18:30',
                    'friday' => '09:30-18:30',
                    'saturday' => '10:00-17:30',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Strasbourg Cathédrale',
                'code' => 'STR001',
                'address' => '15 Place de la Cathédrale',
                'city' => 'Strasbourg',
                'postal_code' => '67000',
                'phone' => '+33 3 88 35 67 89',
                'email' => 'strasbourg.cathedrale@mns.com',
                'latitude' => 48.5816,
                'longitude' => 7.7507,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-17:00',
                    'sunday' => 'closed'
                ]
            ],
            [
                'name' => 'MNS Entrepôt Central',
                'code' => 'ENT001',
                'address' => 'Zone Industrielle Nord, 123 Avenue de la Logistique',
                'city' => 'Roissy-en-France',
                'postal_code' => '95700',
                'phone' => '+33 1 48 62 75 80',
                'email' => 'entrepot.central@mns.com',
                'latitude' => 49.0097,
                'longitude' => 2.5479,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => '06:00-22:00',
                    'tuesday' => '06:00-22:00',
                    'wednesday' => '06:00-22:00',
                    'thursday' => '06:00-22:00',
                    'friday' => '06:00-22:00',
                    'saturday' => '08:00-18:00',
                    'sunday' => '08:00-18:00'
                ]
            ],
        ];

        foreach ($salePoints as $salePoint) {
            SalePoint::create($salePoint);
        }
    }
}