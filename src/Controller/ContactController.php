<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SiteSettingRepository;
use App\Service\MathCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactController extends AbstractController
{
    /** Email de secours utilisé si la setting "contact_email" n'est pas renseignée */
    private const FALLBACK_RECIPIENT = 'epicafebarroux@gmail.com';

    #[Route('/contact', name: 'contact_index', methods: ['GET'])]
    public function index(MathCaptcha $captcha): Response
    {
        return $this->render('contact/index.html.twig', [
            'pageTitle' => 'Contact & Accès',
            'pageSubtitle' => 'Nous trouver au cœur du Barroux',
            'captchaQuestion' => $captcha->generate(),
        ]);
    }

    #[Route('/contact/envoi', name: 'contact_send', methods: ['POST'])]
    public function send(
        Request $request,
        MailerInterface $mailer,
        ValidatorInterface $validator,
        SiteSettingRepository $settings,
        MathCaptcha $captcha,
        #[Target('contact_form')] RateLimiterFactory $contactFormLimiter,
    ): Response {
        // 0) Rate limiting — max 5 envois / 10 min / IP (anti-flood)
        $limiter = $contactFormLimiter->create($request->getClientIp() ?? 'unknown');
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('contact_error', 'Trop de tentatives en peu de temps. Merci de réessayer dans quelques minutes.');
            return $this->redirectToRoute('contact_index', [], Response::HTTP_SEE_OTHER, ['#' => 'contact']);
        }

        // 1) CSRF
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('contact_send', $token)) {
            $this->addFlash('contact_error', 'Erreur de sécurité, merci de recharger la page et de réessayer.');
            return $this->redirectToRoute('contact_index', [], Response::HTTP_SEE_OTHER, ['#' => 'contact']);
        }

        // 2) Honeypot — si rempli, c'est un bot. On fait semblant que tout va bien.
        if (trim((string) $request->request->get('website', '')) !== '') {
            $this->addFlash('contact_success', 'Merci, votre message a bien été envoyé !');
            return $this->redirectToRoute('contact_index', [], Response::HTTP_SEE_OTHER, ['#' => 'contact']);
        }

        // 3) Captcha mathématique — bloque les bots qui contournent le honeypot
        if (!$captcha->verify((string) $request->request->get('captcha_answer', ''))) {
            $this->addFlash('contact_error', 'La vérification anti-spam a échoué. Une nouvelle question vient d\'être générée, merci de réessayer.');
            return $this->redirectToRoute('contact_index', [], Response::HTTP_SEE_OTHER, ['#' => 'contact']);
        }

        // 3) Récupération + nettoyage des champs
        $name    = trim((string) $request->request->get('name', ''));
        $email   = trim((string) $request->request->get('email', ''));
        $message = trim((string) $request->request->get('message', ''));

        // 4) Validation
        $errors = $validator->validate([
            'name'    => $name,
            'email'   => $email,
            'message' => $message,
        ], new Assert\Collection([
            'name'    => [new Assert\NotBlank(message: 'Le nom est requis.'), new Assert\Length(max: 120)],
            'email'   => [new Assert\NotBlank(message: 'L\'email est requis.'), new Assert\Email(message: 'L\'email n\'est pas valide.')],
            'message' => [new Assert\NotBlank(message: 'Le message est requis.'), new Assert\Length(min: 5, max: 5000, minMessage: 'Le message doit contenir au moins 5 caractères.')],
        ]));

        if (count($errors) > 0) {
            $msgs = [];
            foreach ($errors as $err) {
                $msgs[] = $err->getMessage();
            }
            $this->addFlash('contact_error', implode(' ', $msgs));
            return $this->redirectToRoute('contact_index', [], Response::HTTP_SEE_OTHER, ['#' => 'contact']);
        }

        // 5) Destinataire — depuis SiteSettings, fallback dur sur l'adresse Gmail
        $recipient = (string) ($settings->findOneByKey('contact_email')?->getValue() ?: self::FALLBACK_RECIPIENT);

        // 6) Construction & envoi
        $emailObj = (new Email())
            ->from(new Address($recipient, 'Site L\'Épi-Café'))
            ->to($recipient)
            ->replyTo(new Address($email, $name))
            ->subject('[Site] Nouveau message de ' . $name)
            ->text($this->buildPlainTextBody($name, $email, $message))
            ->html($this->buildHtmlBody($name, $email, $message));

        try {
            $mailer->send($emailObj);
            $this->addFlash('contact_success', 'Merci ' . $name . ', votre message a bien été envoyé. Nous vous répondrons dès que possible.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('contact_error', 'Désolé, l\'envoi du message a échoué. Vous pouvez nous contacter directement par téléphone ou email.');
        }

        return $this->redirectToRoute('contact_index', [], Response::HTTP_SEE_OTHER, ['#' => 'contact']);
    }

    private function buildPlainTextBody(string $name, string $email, string $message): string
    {
        return implode("\n", [
            'Nouveau message depuis le formulaire de contact du site Épi-Café',
            '',
            'De      : ' . $name . ' <' . $email . '>',
            'Date    : ' . (new \DateTimeImmutable())->format('d/m/Y H:i'),
            '',
            '------------ Message ------------',
            $message,
            '----------------------------------',
            '',
            'Pour répondre, utilisez simplement la fonction "Répondre" de votre messagerie.',
        ]);
    }

    private function buildHtmlBody(string $name, string $email, string $message): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; color: #2a2520;">
              <h2 style="color: #2d685f; margin: 0 0 12px;">Nouveau message — Épi-Café</h2>
              <p style="color: #6b665e; margin: 0 0 20px;">
                Reçu via le formulaire de contact du site.
              </p>

              <table style="width: 100%; border-collapse: collapse; background: #fbfaf7; border-radius: 8px;">
                <tr>
                  <td style="padding: 10px 14px; border-bottom: 1px solid #e6e0d6; font-weight: bold;">De</td>
                  <td style="padding: 10px 14px; border-bottom: 1px solid #e6e0d6;">{$esc($name)} &lt;{$esc($email)}&gt;</td>
                </tr>
                <tr>
                  <td style="padding: 10px 14px; font-weight: bold;">Message</td>
                  <td style="padding: 10px 14px; line-height: 1.55; white-space: pre-wrap;">{$esc($message)}</td>
                </tr>
              </table>

              <p style="margin: 20px 0 0; color: #6b665e; font-size: 13px;">
                💡 Cliquez sur "Répondre" dans votre messagerie pour répondre directement à {$esc($name)}.
              </p>
            </div>
        HTML;
    }
}
