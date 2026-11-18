<?php

// Local addition for polls.digitalsme.eu, not part of upstream Pollaris.
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Pre-fills the author e-mail of a new poll with the signed-in user's
// address.
//
// Why this matters beyond convenience: polls have no relation to accounts,
// so "My polls" matches on the author e-mail typed into the creation form.
// Left to free typing, a colleague who enters a different address never sees
// their own poll in that list again.
//
// A form type extension rather than overriding PollsController: this only
// adds a file, so upstream's controller keeps being used as shipped.

namespace App\Form;

use App\Entity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class PollFormAuthorEmailExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * @return iterable<class-string>
     */
    public static function getExtendedTypes(): iterable
    {
        return [PollForm::class];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $poll = $event->getData();

            if (!$poll instanceof Entity\Poll) {
                return;
            }

            // Never overwrite an address already on the poll: this listener
            // also runs when an existing poll is edited.
            if ($poll->getAuthorEmail()) {
                return;
            }

            $user = $this->security->getUser();

            if ($user === null) {
                return;
            }

            $identifier = $user->getUserIdentifier();

            // Usernames are e-mail addresses by convention, but nothing
            // enforces it. Filling a non-address would fail validation on a
            // field the user did not touch, so skip it.
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
                return;
            }

            $poll->setAuthorEmail($identifier);
        });
    }
}
