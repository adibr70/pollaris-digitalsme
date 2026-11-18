<?php

// Local addition for polls.digitalsme.eu, not part of upstream Pollaris.
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Lets a signed-in person change their own password. Upstream has no such
// page: passwords are only ever set from the console, so whatever an
// administrator typed when creating the account would stay forever, known to
// them as well as to its owner.

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AccountController extends BaseController
{
    public function __construct(
        private readonly Repository\UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/my/password', name: 'my password')]
    public function password(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Entity\User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createNamedForm('password', Form\SetPasswordForm::class, null, [
            'current_password' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = $form->get('current_password')->getData();

            if (!$this->passwordHasher->isPasswordValid($user, $current)) {
                $form->get('current_password')->addError(
                    new \Symfony\Component\Form\FormError(
                        $this->translator()->trans('password.wrong_current', [], 'digitalsme')
                    )
                );
            } else {
                $user->setPassword(
                    $this->passwordHasher->hashPassword($user, $form->get('password')->getData())
                );
                $this->userRepository->save($user);

                $this->addFlash('success', 'password.changed');

                return $this->redirectToRoute('my polls');
            }
        }

        return $this->render('account/password.html.twig', [
            'form' => $form,
        ]);
    }

    private function translator(): \Symfony\Contracts\Translation\TranslatorInterface
    {
        /** @var \Symfony\Contracts\Translation\TranslatorInterface */
        return $this->container->get('translator');
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'translator' => \Symfony\Contracts\Translation\TranslatorInterface::class,
        ]);
    }
}
