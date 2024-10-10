# Changelog

All notable changes to this project will be documented in this file.

Please view https://github.com/SpartnerNL/Laravel-Excel/releases for the most recent changelog

## [3.1.47] - 2023-02-16

- Support Laravel 10

### Fixed

- Fix Bug Multiple drawings change the behavior of the startCell (#3865).
- Allow configuring read data only in chunks as well.

## [3.1.46] - 2023-01-27

- Support Laravel 10

## [3.1.45] - 2023-01-02

### Added
- Add support for ignoring PHP auto_detect_line_endings INI directive

### Fixed
- Fix the PSR simple cache dependency mess to maintain backwards compatibility and support 3.0 of the interface.

## [3.1.44] - 2022-10-14

### Fixed

- Fix output of `WithFormatData` in combination with `SkipsEmptyRows` (#3760)

### Changed
- Cast empty headings to indexed integer (#3646)
- Adds `isEmptyWhen` to customize is row empty logic. (#3645)

### Fixed

- Fix temporary local files not being cleaned up when setting force_resync_remote config to true (#3623)
- Fix testing for multiple stored files by regex matching (#3631).
- Allow `required_unless` rule (#3660)

## [3.1.40] - 2022-05-02

- Fix testing for multiple stored files by regex matching (#3631).

### Changed

- Adds `WithDefaultStyles` concern to allow configuring the workbook default styles.
- Adds `WithBackgroundColor` concern to allow configuring the workbook default background color.
- Expose the ability to set custom response headers when exporting collections via Exportable

## [3.1.39] - 2022-04-23

### Fixed

- Fix PHP8.1 return type for Failure class (#3588)

## [3.1.38] - 2022-03-24

### Changed

- Adds concern `WithGroupedHeadingRow` to allow imported sheets to group the values of columns with the same header in an array

### Fixed

- Fix for `prepareForValidation` callback not being called when using `SkipsEmptyRows`

## [3.1.37] - 2022-02-28

### Fixed

- Add `@mixin` docblock to all macroable classes to allow for IDE autocompletion of delegate classes
- Fix issue with `Excel::toArray` not allowing nullable reader types for uploaded files

### Changed

- Change default Csv Import to auto-detect the delimiter when not explicitly defined

## [3.1.36] - 2022-02-03

### Fixed

- Fix return type of `FromQuery::query()`

### Changed

- Support Laravel 9
- Added a config setting to specify DB connection
- Added a config setting to specify CSV output encoding
- Added an ability to specify CSV output encoding through csvSettings

## [3.1.35] - 2022-01-04

### Fixed

- Removed cache flush for cell caching as this is already handled by PhpSpreadsheet
- Fix `SkipsEmptyRows` support with the `WithColumnLimit` concern
- Added range support to FormatColumn

## [3.1.34] - 2021-12-2

### Changed

- Change default local_path configuration
- Fix queueImport function to be able to assert chained jobs
- Skipped failure no longer persists in `ToCollection` and `ToArray`.
- Fix missing InteractsWithQueue trait in AppendToSheet jobs
- Add return types to `Row`'s `ArrayAccess` implementation

## [3.1.33] - 2021-08-12

### Fixed

- Make TransactionManager a singleton (#3270)
- Fix Exportable disk options (#3296)
- Delete temporary file after exception in import class (#3312)

## [3.1.32] - 2021-07-08

### Added

- Add assertExportedInRaw assertion (#3255)

### Fixed

- Make commands detect model directory now (#3213)

## [3.1.31] - 2021-06-02

### Added

- Custom heading row formatter can use column index (#3166)
- Added WithFormatData concern (#3154)

### Fixed

- Create failures of rows than didn't exist but where requested in row
  validation
- Fix Bug Formulas are not calculated when import implements
  WithCalculatedFormulas with SkipsEmptyRows #3127
- PhpSpreadsheet 1.18 support

## [3.1.30] - 2021-04-06

### Added

- Octane compatibility

## [3.1.29] - 2021-03-16

### Fixed

- Fix AfterImport Event not being called (#3085)

## [3.1.28] - 2021-03-10

### Added

- Added WithUpsertColumns concern (#3046)
- Added ShouldQueueWithoutChain concern (#3072)

### Fixed

- Limit Phpspreadsheet version to 1.16 until bugfix release
- Fixed issue with not autosizing columns (#3055)
- Fix selecting worksheets by name with chunk reading (#3052)

## [3.1.27] - 2021-02-22

### Added

- Added SkipsEmptyRows concern
- Added HasReferencesToOtherSheets concern to allow import of calculated

### Changed

- Bump minimum PhpSpreadsheet version
- Progressbar NullOutput as fallback

## [3.1.26] - 2020-11-13

### Added

- PHP 8 support

## [3.1.25] - 2020-11-13

### Added

- Added an ability to prepare rows before appending rows to sheet. Just add
  `prepareRows` method for your export class if needed.
- Added an ability to catch exceptions from `QueueExport` job. Just add `failed`
  method for your export class if needed.
- Added an ability to set locale for queued export. Just implement
  `Illuminate\Contracts\Translation\HasLocalePreference` for your export.
- Added `JsonSerializable` support in `Maatwebsite\Excel\Validators\Failure`.
- Added `$maxExceptions` support in `Maatwebsite\Excel\Jobs\ReadChunk.php`.
- Added support to upsert models by implementing the `WithUpserts` concern.

## [3.1.24] - 2020-10-28

### Added

- Added support for `prepareForValidation` on `WithValidation` concern
- Added support for `withValidator` on `WithValidation` concern
- Added `ArrayAccess` to `Row`

### Fixed

- Corrected SkipsErrors doc block

## [3.1.23] - 2020-09-29

### Added

- Added `ignore_empty` setting to `config/excel.php`
- Added `strict_null_comparison` setting to `config/excel.php`

## [3.1.22] - 2020-09-08

- Laravel 8 support
- Lumen improvements

## [3.1.21] - 2020-08-06

### Added

- Added WithProperties concern
- Added default spreadsheet properties config
- Added WithColumnWidths concern
- Added WithStyles concern.
- Config setting to configure cell caching

### Changed

- Sheet titles longer than 31 chars get trimmed.
- Sheet titles with unsupported chars get cleaned.

### Fixed

- Fixed issue with using ShouldAutosize in combination with FromView column
  widths.

## [3.1.20] - 2020-07-22

### Added

- Re-sycing remote temporary file
- Remember row number
- Remember chunk offset
- WithColumnLimit concern
- WithReadFilter concern
- Publishing the stubs

### Changed

- Interacting with queued jobs
- Retry until and middleware on queued imports
- Using WithValidation with FromCollection & FromArray
- Read filters for WithLimit and HeadingRowImport
- Bump of minimum version PhpSpreadsheet

### Fixed

- Fixed test helper docblocks on the Excel facade.
- Fix for importing with a start row beyond the highest row.
- Fixed `BeforeSheet` and `AfterSheet` events receiving exportable instance
  instead of importable when calling on an Import.
- Fix for value binders not working in queued exports.
- Fix when using WithLimit concern when having less rows than the limit.
- Fix AfterImport job being fired twice if not using queueing.
- Raw() method now also available on Exportable.
- Fix for breaking changes in PhpSpreadsheet with empty enclosures.

[Unreleased]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.47...HEAD
[3.1.47]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.46...3.1.47
[3.1.46]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.45...3.1.46
[3.1.45]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.44...3.1.45
[3.1.44]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.43...3.1.44
[3.1.43]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.42...3.1.43
[3.1.42]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.41...3.1.42
[3.1.41]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.40...3.1.41
[3.1.40]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.39...3.1.40
[3.1.39]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.38...3.1.39
[3.1.38]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.37...3.1.38
[3.1.37]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.36...3.1.37
[3.1.36]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.35...3.1.36
[3.1.35]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.34...3.1.35
[3.1.34]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.33...3.1.34
[3.1.33]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.32...3.1.33
[3.1.32]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.31...3.1.32
[3.1.31]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.30...3.1.31
[3.1.30]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.29...3.1.30
[3.1.29]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.28...3.1.29
[3.1.28]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.27...3.1.28
[3.1.27]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.26...3.1.27
[3.1.26]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.25...3.1.26
[3.1.25]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.24...3.1.25
[3.1.24]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.23...3.1.24
[3.1.23]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.22...3.1.23
[3.1.22]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.21...3.1.22
[3.1.21]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.20...3.1.21
[3.1.20]: https://github.com/Maatwebsite/Laravel-Excel/compare/3.1.19...3.1.20

_Older release notes can be found in Github releases._
