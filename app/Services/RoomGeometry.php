<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoomGeometry
{
    public const DEFAULT = [
        'width' => 12, 'depth' => 10,
        'door' => ['wall' => 'south', 'offset' => 5.4, 'width' => 1.2, 'clearance' => 1.2],
        'blocked' => [], 'fixed' => [],
    ];

    public static function rules(): array
    {
        $rules = [
            'layout' => ['required', 'array:width,depth,door,blocked,fixed'],
            'layout.width' => ['required', 'numeric', 'between:3,60'],
            'layout.depth' => ['required', 'numeric', 'between:3,60'],
            'layout.door' => ['required', 'array:wall,offset,width,clearance'],
            'layout.door.wall' => ['required', Rule::in(['north', 'south', 'east', 'west'])],
            'layout.door.offset' => ['required', 'numeric', 'min:0'],
            'layout.door.width' => ['required', 'numeric', 'between:0.8,5'],
            'layout.door.clearance' => ['required', 'numeric', 'between:0.9,5'],
        ];

        foreach (['blocked', 'fixed'] as $kind) {
            $prefix = 'layout.'.$kind;
            $rules[$prefix] = ['present', 'array', 'max:25'];
            $rules[$prefix.'.*'] = ['required', 'array:x,y,width,depth,label'];
            $rules[$prefix.'.*.x'] = ['required', 'numeric', 'min:0'];
            $rules[$prefix.'.*.y'] = ['required', 'numeric', 'min:0'];
            $rules[$prefix.'.*.width'] = ['required', 'numeric', 'gt:0'];
            $rules[$prefix.'.*.depth'] = ['required', 'numeric', 'gt:0'];
            $rules[$prefix.'.*.label'] = ['required', 'string', 'max:60'];
        }

        return $rules;
    }

    public static function isUsable(?array $layout): bool
    {
        return Validator::make(['layout' => $layout], self::rules())->passes() && self::insideRoom($layout);
    }

    public static function insideRoom(array $layout): bool
    {
        $door = $layout['door'];
        $horizontal = in_array($door['wall'], ['north', 'south'], true);
        $wallLength = $horizontal ? $layout['width'] : $layout['depth'];
        $roomDepth = $horizontal ? $layout['depth'] : $layout['width'];

        if ($wallLength < $door['offset'] + $door['width'] || $door['clearance'] > $roomDepth) {
            return false;
        }

        foreach ([...$layout['blocked'], ...$layout['fixed']] as $zone) {
            if ($layout['width'] < $zone['x'] + $zone['width'] || $layout['depth'] < $zone['y'] + $zone['depth']) {
                return false;
            }
        }

        return true;
    }

    public static function normalize(array $layout): array
    {
        $layout['width'] = (float) $layout['width'];
        $layout['depth'] = (float) $layout['depth'];

        foreach (['offset', 'width', 'clearance'] as $field) {
            $layout['door'][$field] = (float) $layout['door'][$field];
        }

        foreach (['blocked', 'fixed'] as $kind) {
            $layout[$kind] = array_values($layout[$kind]);
            foreach ($layout[$kind] as &$zone) {
                foreach (['x', 'y', 'width', 'depth'] as $field) {
                    $zone[$field] = (float) $zone[$field];
                }
            }
            unset($zone);
        }

        return $layout;
    }
}
