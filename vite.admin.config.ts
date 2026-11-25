import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from "vite";

export default defineConfig({
	publicDir: false,
	plugins: [tailwindcss()],
	build: {
		outDir: "admin/public/assets",
		emptyOutDir: false,
		rollupOptions: {
			input: "admin/src/assets/admin.css",
			output: {
				assetFileNames: "admin.[ext]",
			},
		},
	},
});
