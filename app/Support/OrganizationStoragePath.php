<?php

namespace App\Support;

use App\Models\ActivityProposal;
use App\Models\ActivityRequestForm;
use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * Centralised helper for resolving the Supabase Storage folder paths used by
 * organization-scoped uploads (registration, renewal, activity proposal,
 * activity request form).
 *
 * The bucket itself is selected by the `supabase` filesystem disk
 * (SUPABASE_STORAGE_BUCKET) so the paths returned here are bucket-relative —
 * never include the bucket name in `stored_path`.
 *
 * Folder convention:
 *   {organization-slug}-org-{id}/registration
 *   {organization-slug}-org-{id}/renewals/{submission_id?}
 *   {organization-slug}-org-{id}/activity-proposals/{proposal_id}/{field_key?}
 *   {organization-slug}-org-{id}/activity-request-forms/{form_id}/{field_key?}
 *
 * The `-org-{id}` suffix keeps the folder identifiable and avoids collisions
 * when two organizations happen to share a name. If the slug cannot be derived
 * (e.g. missing name) we fall back to `organization-{id}`.
 *
 * IMPORTANT — backwards compatibility:
 *  - This helper is for *new* uploads only.
 *  - Existing files keep working because every read path resolves files via
 *    `attachments.stored_path` (the source of truth), not by recomputing the
 *    folder from the organization name.
 */
final class OrganizationStoragePath
{
    /**
     * Top-level organization folder name. Identifiable + collision-safe.
     */
    public function organizationFolder(Organization $organization): string
    {
        $name = (string) ($organization->organization_name ?? '');
        $slug = Str::slug($name);

        if ($slug !== '') {
            return $slug.'-org-'.(int) $organization->id;
        }

        return 'organization-'.(int) $organization->id;
    }

    public function registrationFolder(Organization $organization): string
    {
        return $this->organizationFolder($organization).'/registration';
    }

    public function renewalFolder(Organization $organization, ?int $submissionId = null): string
    {
        $base = $this->organizationFolder($organization).'/renewals';

        return $submissionId !== null
            ? $base.'/'.$submissionId
            : $base;
    }

    public function activityProposalFolder(
        Organization $organization,
        ?ActivityProposal $proposal = null,
        ?string $fieldKey = null
    ): string {
        $base = $this->organizationFolder($organization).'/activity-proposals';

        if ($proposal !== null && $proposal->getKey() !== null) {
            $base .= '/'.(int) $proposal->getKey();
        }

        $cleanFieldKey = $this->sanitizeFieldKey($fieldKey);
        if ($cleanFieldKey !== '') {
            $base .= '/'.$cleanFieldKey;
        }

        return $base;
    }

    public function activityRequestFormFolder(
        Organization $organization,
        ?ActivityRequestForm $form = null,
        ?string $fieldKey = null
    ): string {
        $base = $this->organizationFolder($organization).'/activity-request-forms';

        if ($form !== null && $form->getKey() !== null) {
            $base .= '/'.(int) $form->getKey();
        }

        $cleanFieldKey = $this->sanitizeFieldKey($fieldKey);
        if ($cleanFieldKey !== '') {
            $base .= '/'.$cleanFieldKey;
        }

        return $base;
    }

    /**
     * Path prefixes a stored_path may legally start with for the given
     * organization. Used by authorization checks that need to verify a
     * file actually belongs to the organization's folder tree (covering
     * both legacy ID-folders and new slug-folders).
     *
     * @return list<string>
     */
    public function organizationPathPrefixes(Organization $organization): array
    {
        $prefixes = [
            $this->organizationFolder($organization).'/',
            (int) $organization->id.'/',
        ];

        return array_values(array_unique($prefixes));
    }

    private function sanitizeFieldKey(?string $fieldKey): string
    {
        if ($fieldKey === null) {
            return '';
        }

        $clean = preg_replace('/[^a-zA-Z0-9_\-]+/', '', trim($fieldKey)) ?? '';

        return $clean;
    }
}
