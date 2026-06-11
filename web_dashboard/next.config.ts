import type { NextConfig } from "next";

const flutterWebBaseUrl = process.env.FLUTTER_WEB_BASE_URL ?? "http://127.0.0.1:7357";
const isProd = process.env.NODE_ENV === "production";

const nextConfig: NextConfig = {
  basePath: "/web_dashboard",
  // Crucial for maintaining clean folder proxy matching behind Nginx
  trailingSlash: true, 
  outputFileTracingRoot: process.cwd(),
  
  async redirects() {
    // 👍 Keep the redirect active on your local machine, but completely bypass it on production
    if (isProd) {
      return [];
    }

    return [
      {
        source: "/",
        destination: "/web_dashboard",
        permanent: false,
        basePath: false,
      },
    ];
  },

  async rewrites() {
    return {
      beforeFiles: [
        {
          source: "/mobile_dashboard",
          destination: `${flutterWebBaseUrl}/`,
          basePath: false,
        },
        {
          source: "/mobile_dashboard/:path*",
          destination: `${flutterWebBaseUrl}/:path*`,
          basePath: false,
        },
        {
          source: "/flutter_bootstrap.js",
          destination: `${flutterWebBaseUrl}/flutter_bootstrap.js`,
          basePath: false,
        },
        {
          source: "/flutter.js",
          destination: `${flutterWebBaseUrl}/flutter.js`,
          basePath: false,
        },
        {
          source: "/main.dart.js",
          destination: `${flutterWebBaseUrl}/main.dart.js`,
          basePath: false,
        },
        {
          source: "/flutter_service_worker.js",
          destination: `${flutterWebBaseUrl}/flutter_service_worker.js`,
          basePath: false,
        },
        {
          source: "/manifest.json",
          destination: `${flutterWebBaseUrl}/manifest.json`,
          basePath: false,
        },
        {
          source: "/favicon.png",
          destination: `${flutterWebBaseUrl}/favicon.png`,
          basePath: false,
        },
        {
          source: "/assets/:path*",
          destination: `${flutterWebBaseUrl}/assets/:path*`,
          basePath: false,
        },
        {
          source: "/canvaskit/:path*",
          destination: `${flutterWebBaseUrl}/canvaskit/:path*`,
          basePath: false,
        },
        {
          source: "/icons/:path*",
          destination: `${flutterWebBaseUrl}/icons/:path*`,
          basePath: false,
        },
      ],
    };
  },
  
  eslint: {
    ignoreDuringBuilds: true,
  },
  
  webpack: (config, { dev }) => {
    if (dev) {
      config.watchOptions = {
        poll: 1500,
        aggregateTimeout: 300,
        ignored: ["**/node_modules/**", "**/.git/**", "**/.next/**"],
      };
    }
    return config;
  },
};

export default nextConfig;