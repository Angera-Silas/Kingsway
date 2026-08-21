<?php

namespace App\API\Services;

/**
 * Single source of truth for sidebar menus.
 *
 * Reads config/role_sidebars.php (keyed by numeric role_id) and normalises it
 * into the flat parent/child item shape used by both the login/refresh path
 * (AuthAPI) and the profile path (UsersAPI::getSidebarItems). Centralising the
 * normalisation here prevents the two code-paths from drifting apart (the old
 * split-brain where login read the file but the profile path read the DB menu).
 */
class SidebarConfigReader
{
    /**
     * Build the normalised sidebar for a role, or [] if the role is undefined.
     */
    public static function forRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        static $config = null;
        if ($config === null) {
            $path = dirname(__DIR__, 2) . '/config/role_sidebars.php';
            $config = file_exists($path) ? (include $path) : [];
        }

        if (!isset($config[$roleId])) {
            return [];
        }

        // IDs: parent = roleId*10000 + groupIndex*100; child = parentId + childIndex + 1
        $items      = [];
        $groupIndex = 0;
        foreach ($config[$roleId] as $item) {
            $parentId = $roleId * 10000 + $groupIndex * 100;
            $subitems = [];
            $subIndex = 1;
            foreach ($item['subitems'] ?? [] as $sub) {
                $subitems[] = self::item($parentId + $subIndex, $parentId, $sub);
                $subIndex++;
            }
            $items[] = self::item($parentId, null, $item, $groupIndex, $subitems);
            $groupIndex++;
        }

        // Every authenticated school-domain staff member may browse the
        // internal merchandise catalogue. The family workspace is resolved
        // from the staff JWT and linked parents record; non-parent staff see
        // a clear access message instead of receiving another login flow.
        if ($roleId > 2) {
            $catalogParent = $roleId * 10000 + 9800;
            $items[] = self::item($catalogParent, null, [
                'label' => 'Products Catalog', 'url' => 'uniform_catalog',
                'icon' => 'fas fa-store', 'subitems' => []
            ], $groupIndex++);
            $familyParent = $roleId * 10000 + 9900;
            $items[] = self::item($familyParent, null, [
                'label' => 'My Family', 'url' => 'my_family',
                'icon' => 'fas fa-house-user', 'subitems' => []
            ], $groupIndex);
        }

        return $items;
    }

    /**
     * Normalise a single sidebar node (group or subitem) to the shared shape.
     */
    private static function item(
        int $id,
        ?int $parentId,
        array $node,
        int $displayOrder = 0,
        array $subitems = []
    ): array {
        $url = $node['url'] ?? null;
        return [
            'id'                    => $id,
            'parent_id'             => $parentId,
            'label'                 => $node['label'],
            'icon'                  => $node['icon'] ?? null,
            'url'                   => $url,
            'route_url'             => $url,
            'domain'                => 'SCHOOL',
            'display_order'         => $displayOrder,
            'subitems'              => $subitems,
            'show_badge'            => false,
            'badge_source'          => null,
            'badge_color'           => 'danger',
            'open_in_new_tab'       => false,
            'requires_confirmation' => false,
            'confirmation_message'  => null,
            'css_class'             => null,
            'tooltip'               => null,
        ];
    }
}
