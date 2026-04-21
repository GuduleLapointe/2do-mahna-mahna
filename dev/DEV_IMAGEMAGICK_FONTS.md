# ImageMagick Font Configuration for Development Environments

## Problem

ImageMagick may fail to find system fonts in development environments, causing:
- `Imagick::queryFonts()` returns an empty array
- `magick -list font` returns no fonts
- PHP scripts using Imagick fail with "font not found" errors

This issue can occur in various development setups:
- macOS with Homebrew-installed ImageMagick
- Development servers (Symfony `server:start`, PHP built-in server)
- Docker containers or virtual environments
- Systems where ImageMagick configuration wasn't properly set up

## Root Cause

ImageMagick relies on configuration files (`type.xml`, `type-*.xml`) to locate system fonts. In development environments, these files may:
- Contain incorrect or missing font paths
- Point to non-existent directories
- Be missing entirely
- Not include user-installed fonts

## Solution

### Step 1: Generate Font Configuration File

Use the official `imagick_type_gen` script to generate a comprehensive font configuration:

```bash
# Navigate to the script directory
cd dev

# Run the script (outputs to stdout)
perl imagick_type_gen > ~/.magick/type.xml
```

This script:
- Scans standard font directories
- Generates proper XML configuration
- Handles various font formats (TTF, OTF, TTC)
- Creates clean font names for ImageMagick

### Step 2: Configure ImageMagick to Use the File

#### Option A: Environment Variable (Recommended)

Set the `MAGICK_FONT_PATH` environment variable to point to your configuration:

```bash
# For current terminal session
export MAGICK_FONT_PATH=~/.magick

# For Symfony development server
MAGICK_FONT_PATH=~/.magick symfony server:start

# For PHP built-in server
MAGICK_FONT_PATH=~/.magick php -S localhost:8082 -t output/

# For permanent configuration (add to ~/.bashrc or ~/.zshrc)
echo 'export MAGICK_FONT_PATH=~/.magick' >> ~/.bashrc
source ~/.bashrc
```

#### Option B: System Configuration (Alternative)

Copy the configuration to ImageMagick's system directory:

```bash
# Find ImageMagick configuration directory
IM_CONFIG_DIR=$(magick -list configure | grep CONFIGURE_PATH | awk '{print $2}')

# Copy configuration (requires sudo)
sudo cp ~/.magick/type.xml "$IM_CONFIG_DIR/type.xml"
```

**Note:** System configuration will be overwritten on ImageMagick updates.

### Step 3: Verify Configuration

Check that ImageMagick can now find fonts:

```bash
# List available fonts (ImageMagick CLI)
magick -list font | head -20

# Check from PHP
php -r 'print_r(Imagick::queryFonts());'

# Quick test script
php -r '
$queryFonts = Imagick::queryFonts();
echo "Found " . count($queryFonts) . " fonts\n";
echo "Sample fonts: " . implode(", ", array_slice($queryFonts, 0, 5)) . "\n";
'
```

## Common Font Directories

The script searches these standard font locations:

**macOS:**
- `/System/Library/Fonts/Supplemental/` - System fonts
- `/System/Library/Fonts/` - Additional system fonts
- `/Library/Fonts/` - User-installed fonts (all users)
- `~/Library/Fonts/` - User-specific fonts

**Linux:**
- `/usr/share/fonts/` - System fonts
- `/usr/local/share/fonts/` - Locally installed fonts
- `~/.fonts/` - User fonts
- `~/.local/share/fonts/` - User local fonts

**Windows:**
- `C:\Windows\Fonts\` - System fonts
- `%APPDATA%\Microsoft\Windows\Fonts\` - User fonts

## Alternative: Direct Font Path

If you prefer not to generate a configuration file:

```bash
# Set font path directly
export MAGICK_FONT_PATH="/System/Library/Fonts/Supplemental:/System/Library/Fonts:/Library/Fonts:$HOME/Library/Fonts"

# For development servers
MAGICK_FONT_PATH="/usr/share/fonts:/usr/local/share/fonts" symfony server:start
```

## Troubleshooting

### Fonts Still Not Found?

1. **Check environment variable:**
   ```bash
   echo $MAGICK_FONT_PATH
   ```

2. **Verify configuration file:**
   ```bash
   ls -la ~/.magick/type.xml
   head -20 ~/.magick/type.xml
   ```

3. **Check ImageMagick configuration:**
   ```bash
   magick -list configure | grep -E '(CONFIGURE|FONT)'
   ```

4. **Test with direct path:**
   ```bash
   export MAGICK_FONT_PATH="/System/Library/Fonts/Supplemental:/Library/Fonts"
   magick -list font | head -5
   ```

5. **Check file permissions:**
   ```bash
   chmod 644 ~/.magick/type.xml
   ```

### Common Issues

- **Permission denied:** Ensure you have read access to font files
- **Empty configuration:** Verify the script found fonts in your directories
- **Wrong ImageMagick version:** Check `magick --version` matches your PHP Imagick version
- **Configuration not loaded:** Restart your development server after setting variables

## Best Practices

1. **Use environment variables** for development to avoid modifying system files
2. **Document the requirement** in your project's development setup guide
3. **Add to team onboarding** so all developers configure their environments consistently
4. **Consider Docker** if your team uses containerized development environments

## References

- [ImageMagick Font Configuration](https://imagemagick.org/script/resources.php)
- [ImageMagick Type Configuration](https://imagemagick.org/script/resources.php#type)
- [Freetype Documentation](https://www.freetype.org/)
- [macOS Font Locations](https://support.apple.com/guide/mac-help/use-fonts-on-mac-mh14070/mac)

## Notes

- **No code changes required** - This solution works with existing PHP scripts
- **Environment-specific** - Configuration applies only to development, not production
- **Portable** - Works across different operating systems
- **Maintainable** - Survives ImageMagick updates when using environment variables
