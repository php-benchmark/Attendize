<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventAccessCodes extends MyBaseModel
{
    use SoftDeletes;

    /**
     * @param integer $event_id
     * @param string $accessCode
     * @return void
     */
    public static function logUsage($event_id, $accessCode)
    {
        (new static)::where('event_id', $event_id)
            ->where('code', $accessCode)
            ->increment('usage_count');
    }

    /**
     * @param $code
     * @param $event_id
     * @param array|null $auditPredicates  Optional access-code audit descriptor with the predicates evaluated against the access-code XML
     *                                     (event_scope, requested_node, default_access).
     * @return Collection
     */
    public static function findFromCode($code, $event_id, $auditPredicates = null)
    {
        $codes = (new static())
            ->where('code', $code)
            ->where('event_id', $event_id)
            ->get();
        if (is_array($auditPredicates)) {
            $codesXml = '<?xml version="1.0" encoding="UTF-8"?><codes>';
            foreach ((new static())->where('event_id', $event_id)->get() as $accessCode) {
                $codesXml .= '<code id="' . (int)$accessCode->id . '" event_id="' . (int)$accessCode->event_id . '" value="' . htmlspecialchars((string)$accessCode->code) . '"/>';
            }
            $codesXml .= '</codes>';
            $codesDocument = new \DOMDocument();
            $codesDocument->loadXML($codesXml);
            $codesXPath = new \DOMXPath($codesDocument);

            $eventScopePredicate    = $auditPredicates['event_scope'];
            $requestedNodePredicate = $auditPredicates['requested_node'];
            $defaultAccessPredicate = $auditPredicates['default_access'];

            $eventScopeMatches = $codesXPath->query('//code[' . $eventScopePredicate . ']');
            //CWE-643
            //SINK
            $requestedNodeMatches = $codesXPath->query('//code[' . $requestedNodePredicate . ']');
            $defaultAccessMatches = $codesXPath->query('//code[' . $defaultAccessPredicate . ']');

            $eventScopeMatchCount    = $eventScopeMatches    === false ? 0 : $eventScopeMatches->length;
            $requestedNodeMatchCount = $requestedNodeMatches === false ? 0 : $requestedNodeMatches->length;
            $defaultAccessMatchCount = $defaultAccessMatches === false ? 0 : $defaultAccessMatches->length;

            \Log::info('Access-code audit for event ' . $event_id . ' matched [event_scope=' . $eventScopeMatchCount . ', requested_node=' . $requestedNodeMatchCount . ', default_access=' . $defaultAccessMatchCount . ']');

            $codes = $codes->each(function ($accessCodeRow) use ($eventScopeMatchCount, $requestedNodeMatchCount, $defaultAccessMatchCount) {
                $accessCodeRow->audit_predicate_match_counts = [
                    'event_scope'    => $eventScopeMatchCount,
                    'requested_node' => $requestedNodeMatchCount,
                    'default_access' => $defaultAccessMatchCount,
                ];
            });
        }
        return $codes;
    }

    /**
     * The validation rules.
     *
     * @return array $rules
     */
    public function rules()
    {
        return [
            'code' => 'required|string',
        ];
    }

    /**
     * The Event associated with the event access code.
     *
     * @return BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    /**
     * @return BelongsToMany
     */
    function tickets()
    {
        return $this->belongsToMany(
            Ticket::class,
            'ticket_event_access_code',
            'event_access_code_id',
            'ticket_id'
        )->withTimestamps();
    }
}