# Demo images

Drop your own images here and run `wp mira seed` again: each file found replaces the
image the seeder would otherwise paint with GD. Missing files are simply painted, so you
can provide all, some or none of them.

- Formats: `.jpg`, `.jpeg`, `.png` or `.webp` (the base name must match exactly).
- Imported at most 2400 px on the longest side, then saved as WebP (or JPEG without WebP support).
- Keep each file under about 500 KB: they ship in the repository zip that WordPress
  Playground downloads, and the Playground seed must stay under 60 seconds.
- Only use images you own or that are licensed for this use (no hotlinking: files are
  copied into the uploads folder).

## Home page

| File | Used for | Advice |
| --- | --- | --- |
| `hero` | Full-width photo behind the home title | Landscape, at least 2000 px wide: an interior with framed prints, or the studio. Keep the left half calm, the title sits there. Without it, a gallery room is painted from the first three artworks. |
| `studio` | "About the studio" section | Portrait or square: the work table, hands at work, materials. Without it, an artwork is shown. |

Both can also be changed later in **Appearance → Customize → Home page**.

## Artworks

Shop products use the image of the artwork they reproduce, so these also change the shop.
Matching the orientation and size of each artwork keeps the "to scale" preview and the
wall labels believable.

| File | Title | Series | Orientation, size |
| --- | --- | --- | --- |
| `artwork-01` | Morning Swell | Tidal Hours | portrait, 40 × 50 cm |
| `artwork-02` | Low Tide, Late Sun | Tidal Hours | landscape, 50 × 40 cm |
| `artwork-03` | The Lighthouse Keeps Time | Tidal Hours | square, 30 × 30 cm |
| `artwork-04` | Salt and Apricot | Tidal Hours | portrait, 30 × 40 cm |
| `artwork-05` | Slack Water | Tidal Hours | landscape, 60 × 45 cm |
| `artwork-06` | Harbour Blue | Tidal Hours | portrait, 40 × 50 cm |
| `artwork-07` | Ebb | Tidal Hours | square, 30 × 30 cm |
| `artwork-08` | Small Hours in the Greenhouse | Garden of Small Things | portrait, 40 × 50 cm |
| `artwork-09` | Seed Catalogue No. 3 | Garden of Small Things | landscape, 50 × 40 cm |
| `artwork-10` | Marigold Weather | Garden of Small Things | square, 30 × 30 cm |
| `artwork-11` | Pressed Between Pages | Garden of Small Things | portrait, 30 × 40 cm |
| `artwork-12` | Volunteer Poppies | Garden of Small Things | landscape, 60 × 45 cm |
| `artwork-13` | Window Box | Garden of Small Things | portrait, 40 × 50 cm |
| `artwork-14` | The Fern Room | Garden of Small Things | square, 30 × 30 cm |
| `artwork-15` | Map of a Quiet Street | Night Cartography | portrait, 40 × 50 cm |
| `artwork-16` | Elevation at Midnight | Night Cartography | landscape, 50 × 40 cm |
| `artwork-17` | Lantern Contours | Night Cartography | square, 30 × 30 cm |
| `artwork-18` | Where the River Bends | Night Cartography | portrait, 30 × 40 cm |
| `artwork-19` | Moon over the Survey Line | Night Cartography | landscape, 60 × 45 cm |
| `artwork-20` | Isobars for Insomniacs | Night Cartography | square, 30 × 30 cm |
