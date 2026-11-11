import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { login, logout, signup } from "../src/lib/actions";
import { getWorkspaceIdentity, requireAdmin } from "../src/lib/data";

const { auth, readProfile } = vi.hoisted(() => ({
  auth: {
    getClaims: vi.fn(),
    signInWithPassword: vi.fn(),
    signOut: vi.fn(),
    signUp: vi.fn(),
  },
  readProfile: vi.fn(),
}));

vi.mock("server-only", () => ({}));
vi.mock("next/cache", () => ({ revalidatePath: vi.fn() }));
vi.mock("next/navigation", () => ({
  redirect: (path: string) => { throw new Error("Redirect: " + path); },
  notFound: () => { throw new Error("Not found"); },
}));
vi.mock("../src/lib/supabase", () => ({
  createClient: async () => ({
    auth,
    from: () => ({ select: () => ({ eq: () => ({ maybeSingle: readProfile }) }) }),
  }),
}));

function credentials() {
  const form = new FormData();
  form.set("email", "student@example.test");
  form.set("password", "strong-password");
  return form;
}

beforeEach(() => {
  vi.resetAllMocks();
  vi.stubEnv("NEXT_PUBLIC_SUPABASE_URL", "https://campus.example.test");
  vi.stubEnv("NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY", "sb_publishable_test-fixture");
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", "https://campusspace.example.test");
});

afterEach(() => vi.unstubAllEnvs());

describe("authentication actions and workspace authorization", () => {
  it.each([
    ["NEXT_PUBLIC_SUPABASE_URL", "ISI_PROJECT_URL_SUPABASE"],
    ["NEXT_PUBLIC_SUPABASE_URL", "https://"],
    ["NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY", "sb_secret_"],
  ])("requires usable settings before attempting authentication (%s, %s)", async (name, value) => {
    vi.stubEnv(name, value);

    await expect(getWorkspaceIdentity()).resolves.toMatchObject({ preview: true, client: null });
    await expect(login({}, credentials())).resolves.toHaveProperty("error");
    expect(auth.getClaims).not.toHaveBeenCalled();
    expect(auth.signInWithPassword).not.toHaveBeenCalled();
  });

  it("validates login before making a Supabase request", async () => {
    await expect(login({}, new FormData())).resolves.toHaveProperty("error");
    expect(auth.signInWithPassword).not.toHaveBeenCalled();
  });

  it("signs in using the submitted credentials and redirects on success", async () => {
    auth.signInWithPassword.mockResolvedValue({ error: null });
    await expect(login({}, credentials())).rejects.toThrow("Redirect: /");
    expect(auth.signInWithPassword).toHaveBeenCalledWith({
      email: "student@example.test", password: "strong-password",
    });
  });

  it("sends confirmation instructions without passing user-supplied roles", async () => {
    const form = credentials();
    form.set("fullName", "Campus Student");
    form.set("role", "admin");
    auth.signUp.mockResolvedValue({ data: { session: null }, error: null });

    await expect(signup({}, form)).resolves.toHaveProperty("success");
    expect(auth.signUp).toHaveBeenCalledWith({
      email: "student@example.test",
      password: "strong-password",
      options: {
        data: { full_name: "Campus Student" },
        emailRedirectTo: "https://campusspace.example.test/auth/confirm",
      },
    });
  });

  it("redirects unsigned users in configured mode and hides the admin workspace from students", async () => {
    auth.getClaims.mockResolvedValueOnce({ data: null });
    await expect(getWorkspaceIdentity()).rejects.toThrow("Redirect: /login");

    auth.getClaims.mockResolvedValue({ data: { claims: { sub: "student-id", role: "admin" } } });
    readProfile.mockResolvedValue({ data: { id: "student-id", role: "student" } });
    await expect(requireAdmin()).rejects.toThrow("Not found");
  });

  it("allows an assigned admin and clears the local session on logout", async () => {
    auth.getClaims.mockResolvedValue({ data: { claims: { sub: "staff-id" } } });
    readProfile.mockResolvedValue({ data: { id: "staff-id", role: "admin" } });
    auth.signOut.mockResolvedValue({ error: null });

    await expect(requireAdmin()).resolves.toMatchObject({ profile: { role: "admin" } });
    await expect(logout()).rejects.toThrow("Redirect: /login");
    expect(auth.signOut).toHaveBeenCalledWith({ scope: "local" });
  });
});
