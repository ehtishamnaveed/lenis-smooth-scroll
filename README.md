# Lenis Smooth Scroll for WordPress

A professional, performance-focused WordPress plugin that integrates the [Lenis](https://github.com/darkroomengineering/lenis) smooth scrolling library into your website.

## Features

- **Easy Integration:** Seamlessly adds Lenis smooth scrolling to any WordPress theme.
- **Admin Settings Page:** Comprehensive settings panel to customize scrolling behavior without touching code.
- **Customizable Performance:** Fine-tune duration, lerp, and easing functions.
- **Advanced Controls:** Configure wheel multiplier, touch multiplier, and orientation.
- **GSAP Integration:** Built-in support for GSAP ScrollTrigger.
- **Selective Loading:** Choose to load Lenis globally, only on the home page, or on specific pages/posts.
- **CSS Overrides:** Option to automatically include CSS fixes for common overflow issues.

## Installation

1. Download the `Lenis-Smooth-Scroll.zip` from the [Releases](https://github.com/ehtishamnaveed/lenis-smooth-scroll/releases) page.
2. Log in to your WordPress admin dashboard.
3. Navigate to `Plugins` -> `Add New` -> `Upload Plugin`.
4. Select the downloaded ZIP file and click `Install Now`.
5. Activate the plugin.

## Configuration

Once activated, you can find the settings under `Settings` -> `Lenis Scroll`.

### Basic Settings
- **Enable Smooth Scroll:** Toggle the smooth scrolling effect on or off.
- **Duration:** Set the scroll animation duration (default is 1.2 seconds).
- **Lerp:** Adjust the linear interpolation (0-1). Lower values result in smoother, slower scrolling.
- **Easing:** Choose from several easing functions (Linear, Quad, Cubic, etc.).

### Advanced Settings
- **Smooth Wheel:** Enable smooth scrolling for mouse wheel input.
- **Anchors:** Enable smooth scroll-to-anchor functionality.
- **Sync Touch:** Experimental feature to sync touch devices.
- **Orientation:** Choose between vertical or horizontal scrolling.

## Development

The plugin is built with a single-class architecture for simplicity and performance. It enqueues the Lenis library via Unpkg CDN for the latest version and best performance.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
