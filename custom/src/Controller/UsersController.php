<?php

// Local addition for polls.digitalsme.eu, not part of upstream Pollaris.
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Upstream manages accounts only through the app:user:create command: there
// is no listing, no role change and no deletion. This controller provides
// them to administrators.
//
// Deleting an account also deletes the polls it created. Polls hold no
// relation to accounts, so they are matched on the author e-mail, the same
// way the "My polls" page does.

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UsersController extends BaseController
{
    public function __construct(
        private readonly Repository\UserRepository $userRepository,
        private readonly Repository\PollRepository $pollRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/users', name: 'users')]
    public function index(): Response
    {
        return $this->render('users/index.html.twig', [
            'users' => $this->userRepository->findBy([], ['createdAt' => 'ASC']),
            'pollCounts' => $this->pollCountsByAuthor(),
            'currentUsername' => $this->getUser()?->getUserIdentifier(),
            'administratorsCount' => $this->administratorsCount(),
        ]);
    }

    #[Route('/users/new', name: 'new user')]
    public function new(Request $request): Response
    {
        $form = $this->createNamedForm('new_user', Form\NewUserForm::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new Entity\User();
            $user->setUsername(trim($form->get('username')->getData()));

            // Colleagues get ROLE_USER only. ROLE_ADMIN grants the
            // back-office, where every poll on the instance can be read and
            // deleted, so it is opt-in rather than the default the console
            // command applies.
            $user->setRoles(
                $form->get('administrator')->getData() === true
                    ? ['ROLE_USER', 'ROLE_ADMIN']
                    : ['ROLE_USER']
            );

            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $form->get('password')->getData())
            );

            // Reuse the entity's own constraints, including the uniqueness of
            // the username, rather than re-checking by hand.
            $errors = $this->validator->validate($user);

            if (count($errors) === 0) {
                $this->userRepository->save($user);
                $this->addFlash('success', 'users.created');

                return $this->redirectToRoute('users');
            }

            foreach ($errors as $error) {
                $form->addError(new \Symfony\Component\Form\FormError((string) $error->getMessage()));
            }
        }

        return $this->render('users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/{id}/role', name: 'change user role', methods: ['POST'])]
    public function changeRole(Entity\User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('change-user-role', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        $makeAdministrator = $request->request->getString('role') === 'admin';

        // Removing the last administrator would lock everyone out of the
        // back-office, and of this page.
        if (!$makeAdministrator && $this->isLastAdministrator($user)) {
            $this->addFlash('error', 'users.last_administrator');

            return $this->redirectToRoute('users');
        }

        $user->setRoles($makeAdministrator ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER']);
        $this->userRepository->save($user);

        $this->addFlash('success', 'users.role_changed');

        return $this->redirectToRoute('users');
    }

    #[Route('/users/{id}/deletion', name: 'delete user')]
    public function deletion(Entity\User $user, Request $request): Response
    {
        $polls = $this->pollsOf($user);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete-user', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException();
            }

            // Deleting your own account while signed in leaves the session
            // pointing at a user that no longer exists.
            if ($user->getUserIdentifier() === $this->getUser()?->getUserIdentifier()) {
                $this->addFlash('error', 'users.cannot_delete_self');

                return $this->redirectToRoute('users');
            }

            if ($this->isLastAdministrator($user)) {
                $this->addFlash('error', 'users.last_administrator');

                return $this->redirectToRoute('users');
            }

            // The polls go first: once the account is gone, nothing links
            // them to anyone and they would linger unreachable from any list.
            foreach ($polls as $poll) {
                $this->pollRepository->remove($poll, false);
            }

            $this->userRepository->remove($user);

            $this->addFlash('success', 'users.deleted');

            return $this->redirectToRoute('users');
        }

        return $this->render('users/deletion.html.twig', [
            'user' => $user,
            'pollsCount' => count($polls),
            'isSelf' => $user->getUserIdentifier() === $this->getUser()?->getUserIdentifier(),
            'isLastAdministrator' => $this->isLastAdministrator($user),
        ]);
    }

    /**
     * @return array<int, Entity\Poll>
     */
    private function pollsOf(Entity\User $user): array
    {
        $queryBuilder = $this->pollRepository->createQueryBuilder('p');
        $queryBuilder->where('ILIKE(p.authorEmail, :email) = TRUE');
        $queryBuilder->setParameter('email', $user->getUserIdentifier());

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return array<string, int>
     */
    private function pollCountsByAuthor(): array
    {
        $rows = $this->pollRepository->createQueryBuilder('p')
            ->select('LOWER(p.authorEmail) AS email, COUNT(p.id) AS total')
            ->where('p.authorEmail IS NOT NULL')
            ->groupBy('email')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['email']] = (int) $row['total'];
        }

        return $counts;
    }

    private function administratorsCount(): int
    {
        $count = 0;
        foreach ($this->userRepository->findAll() as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $count++;
            }
        }

        return $count;
    }

    private function isLastAdministrator(Entity\User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true)
            && $this->administratorsCount() <= 1;
    }
}
