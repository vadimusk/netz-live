<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/** Updates visit data. */
class Visits {
  public const KEYS = [
    'visits'
  ];

  /**
   * Updates the visit data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    if (
      getenv('CLOUDFLARE_API_TOKEN') === ''
      || getenv('CLOUDFLARE_ZONE_ID') === ''
    ) {
      return;
    }

    $curl = curl_init();

    curl_setopt(
      $curl,
      CURLOPT_URL,
      'https://api.cloudflare.com/client/v4/graphql'
    );

    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . getenv('CLOUDFLARE_API_TOKEN'),
      'Content-Type: application/json'
    ]);

    // Daily totals, rather than the minute-level groups the upstream project
    // reads: those are an Enterprise dataset, and a zone without it is
    // refused access to the field outright. Daily groups are available on
    // every plan. A few days are re-read each run because the most recent
    // day is still accumulating.
    $zoneId    = getenv('CLOUDFLARE_ZONE_ID');
    $time      = $database->getLatestQuarterHourTimestamp();
    $startDate = gmdate('Y-m-d', $time - 3 * 24 * 60 * 60);
    $endDate   = gmdate('Y-m-d', $time + 24 * 60 * 60);

    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
      'query' => <<<QUERY
        query {
          viewer {
            zones(
              filter: {
                zoneTag: "{$zoneId}"
              }
            ) {
              httpRequests1dGroups(
                filter: {
                  date_geq: "{$startDate}",
                  date_lt: "{$endDate}"
                },
                orderBy: [date_ASC],
                limit: 100
              ) {
                dimensions {
                  date
                }
                sum {
                  pageViews
                }
              }
            }
          }
        }
      QUERY
    ]));

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $rawData = curl_exec($curl);

    if ($rawData === false) {
      throw new DataException('Failed to read data');
    }

    $jsonData = json_decode($rawData, true);

    if (
      !isset($jsonData['data']['viewer']['zones'][0]['httpRequests1dGroups'])
      || !is_array($jsonData['data']['viewer']['zones'][0]['httpRequests1dGroups'])
    ) {
      throw new DataException('Missing data');
    }

    $data = [];

    foreach (
      $jsonData['data']['viewer']['zones'][0]['httpRequests1dGroups'] as $item
    ) {
      if (!is_array($item)) {
        throw new DataException('Invalid item');
      }

      $data[] = self::getDatum($item);
    }

    $database->update(self::KEYS, $data);
  }

  /**
   * Returns the datum for an item.
   *
   * @param array $item The item
   *
   * @throws DataException If the data was invalid
   */
  private static function getDatum(array $item): array {
    if (!isset($item['dimensions']['date'])) {
      throw new DataException('Missing time');
    }

    if (!isset($item['sum']['pageViews'])) {
      throw new DataException('Missing visits');
    }

    if (!is_int($item['sum']['pageViews'])) {
      throw new DataException('Invalid visits: ' . $item['sum']['pageViews']);
    }

    // the day's total is recorded against its first quarter hour, so that
    // summing the column over a day, week or month gives the right figure
    return [
      Time::normalise($item['dimensions']['date'] . ' 00:00', 15),
      $item['sum']['pageViews']
    ];
  }
}
