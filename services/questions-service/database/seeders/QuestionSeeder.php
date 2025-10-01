<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;
use App\Models\Answer;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // FAQ Questions
        $questions = [
            [
                'title' => 'Comment puis-je suivre ma commande ?',
                'body' => 'Je voudrais savoir comment faire le suivi de ma commande après l\'avoir passée.',
                'answers' => [
                    'Vous pouvez suivre votre commande en vous connectant à votre compte et en accédant à la section "Mes commandes". Vous y trouverez le statut de livraison en temps réel.',
                    'Vous recevrez également un email de confirmation avec un lien de suivi après l\'expédition de votre commande.'
                ]
            ],
            [
                'title' => 'Quels sont les délais de livraison ?',
                'body' => 'Combien de temps faut-il pour recevoir ma commande ?',
                'answers' => [
                    'Les délais de livraison varient selon votre localisation : 24-48h en France métropolitaine, 3-5 jours en Europe, 7-10 jours pour le reste du monde.',
                    'Pour les commandes urgentes, nous proposons une livraison express en 24h (supplément applicable).'
                ]
            ],
            [
                'title' => 'Comment retourner un article ?',
                'body' => 'Quelle est la procédure pour retourner un produit qui ne me convient pas ?',
                'answers' => [
                    'Vous avez 30 jours pour retourner un article. Connectez-vous à votre compte, allez dans "Mes commandes" et cliquez sur "Retourner cet article".',
                    'Les frais de retour sont gratuits pour les articles défectueux. Pour les autres retours, les frais sont à votre charge.'
                ]
            ],
            [
                'title' => 'Quels modes de paiement acceptez-vous ?',
                'body' => 'Quelles sont les options de paiement disponibles ?',
                'answers' => [
                    'Nous acceptons les cartes de crédit (Visa, Mastercard, American Express), PayPal, et les virements bancaires.',
                    'Pour plus de sécurité, tous les paiements sont cryptés et traités par des partenaires certifiés PCI DSS.'
                ]
            ],
            [
                'title' => 'Comment contacter le service client ?',
                'body' => 'J\'ai besoin d\'aide, comment puis-je vous joindre ?',
                'answers' => [
                    'Notre service client est disponible du lundi au vendredi de 9h à 18h par téléphone, email ou chat en direct.',
                    'Vous pouvez aussi consulter notre centre d\'aide en ligne pour trouver des réponses aux questions les plus fréquentes.'
                ]
            ]
        ];

        foreach ($questions as $questionData) {
            $question = Question::create([
                'title' => $questionData['title'],
                'body' => $questionData['body']
            ]);

            foreach ($questionData['answers'] as $answerText) {
                Answer::create([
                    'question_id' => $question->id,
                    'body' => $answerText
                ]);
            }
        }
    }
}