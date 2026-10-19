import assert from "node:assert/strict";
import { test } from "node:test";
import {
    branchColorStyle,
    branchTextColor,
    isDarkBrandColor,
} from "../../resources/js/lib/branch-color.ts";

await test("treats near-black brands as dark", () => {
    assert.equal(isDarkBrandColor("#000000"), true);
    assert.equal(isDarkBrandColor("#171717"), true);
    assert.equal(isDarkBrandColor("#f53003"), false);
    assert.equal(isDarkBrandColor("#ffffff"), false);
});

await test("dark brands get a solid chip with white text", () => {
    assert.deepEqual(branchColorStyle("#000000"), {
        color: "#ffffff",
        borderColor: "color-mix(in srgb, #000000 65%, #ffffff)",
        backgroundColor: "#000000",
    });
});

await test("saturated brands keep tinted chrome", () => {
    assert.deepEqual(branchColorStyle("#f53003"), {
        color: "#f53003",
        borderColor: "color-mix(in srgb, #f53003 35%, transparent)",
        backgroundColor: "color-mix(in srgb, #f53003 12%, transparent)",
    });
});

await test("dark brand titles flip to white in dark mode", () => {
    assert.equal(branchTextColor("#000000"), "light-dark(#000000, #ffffff)");
    assert.equal(branchTextColor("#f53003"), "#f53003");
    assert.equal(branchTextColor(null), undefined);
});
